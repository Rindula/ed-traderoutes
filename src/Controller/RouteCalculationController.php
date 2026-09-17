<?php

namespace App\Controller;

use App\Domain\Route\RouteCalculationRequest;
use App\Domain\Route\RouteCalculator;
use App\Domain\Route\RouteTimeEstimates;
use App\Domain\Route\MultiLegRoute;
use App\Domain\Route\MultiLegRouteLeg;
use App\Domain\Route\MultiStopRouteCalculationRequest;
use App\Domain\Route\MultiStopRouteCalculator;
use App\Domain\Route\TradeRoute;
use App\Entity\RouteSnapshot;
use App\Entity\User;
use App\Repository\MarketObservationRepository;
use App\Repository\RouteSnapshotRepository;
use App\Repository\StationRepository;
use App\Repository\SystemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RouteCalculationController extends AbstractController
{
    public function __construct(
        private readonly SystemRepository $systems,
        private readonly StationRepository $stations,
        private readonly MarketObservationRepository $observations,
        private readonly RouteSnapshotRepository $snapshots,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?MultiStopRouteCalculator $multiLegCalculator = null,
    ) {}

    #[Route('/api/routes/calculate', name: 'route_calculate', methods: ['POST'])]
    public function calculate(Request $request, RouteCalculator $calculator): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'authentication_required'], 401);
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                throw new \InvalidArgumentException('The request body must be an object.');
            }
            $originName = $payload['originSystem'] ?? null;
            $origin = is_string($originName) ? $this->systems->findByName($originName) : null;
            if (!$origin) {
                throw new \InvalidArgumentException('originSystem must name a known system.');
            }
            $allowedTypes = $payload['stationTypes'] ?? [];
            if (!is_array($allowedTypes)) {
                throw new \InvalidArgumentException('stationTypes must be an array.');
            }
            $common = [
                'originSystem' => $origin,
                'shipJumpRange' => $this->number($payload, 'shipJumpRange'),
                'maxJumpDistance' => $this->number($payload, 'maxJumpDistance'),
                'cargoCapacity' => $this->integer($payload, 'cargoCapacity'),
                'asOf' => new \DateTimeImmutable(),
                'maxDataAgeSeconds' => isset($payload['maxDataAgeSeconds']) ? $this->integer($payload, 'maxDataAgeSeconds') : RouteCalculationRequest::DEFAULT_MAX_DATA_AGE_SECONDS,
                'landingClassFilter' => isset($payload['landingClass']) && is_string($payload['landingClass']) ? $payload['landingClass'] : null,
                'allowedStationTypes' => array_values($allowedTypes),
                'timeEstimates' => new RouteTimeEstimates(
                    secondsPerJump: isset($payload['secondsPerJump']) ? $this->number($payload, 'secondsPerJump') : 45.0,
                    secondsPerTrade: isset($payload['secondsPerTrade']) ? $this->number($payload, 'secondsPerTrade') : 30.0,
                ),
            ];

            $multiLeg = $this->isMultiLegRequest($payload);
            $requestData = $multiLeg
                ? new MultiStopRouteCalculationRequest(
                    ...$common,
                    maxTotalJumps: isset($payload['maxTotalJumps']) ? $this->integer($payload, 'maxTotalJumps') : 20,
                    maxStops: isset($payload['maxTradeStops'])
                        ? $this->integer($payload, 'maxTradeStops')
                        : (isset($payload['maxStops']) ? $this->integer($payload, 'maxStops') : 3),
                    returnToStart: $this->returnToStart($payload),
                )
                : new RouteCalculationRequest(...$common);
        } catch (\Throwable $exception) {
            return $this->json(['error' => 'invalid_route_request', 'message' => $exception->getMessage()], 422);
        }

        $markets = $this->stations->findAccessibleMarkets();
        $observations = $this->observations->findAll();
        $isMultiLeg = $requestData instanceof MultiStopRouteCalculationRequest;
        $multiLegCalculator = $this->multiLegCalculator ?? new MultiStopRouteCalculator();
        $route = $isMultiLeg
            ? $multiLegCalculator->calculateBest($requestData, $markets, $observations)
            : $calculator->calculateBest($requestData, $markets, $observations);
        if ($route === null) {
            return $this->json(['route' => null, 'message' => 'No executable trade found.'], 404);
        }

        $firstLeg = $isMultiLeg ? $route->legs()[0]->tradeRoute() : $route;
        $snapshot = new RouteSnapshot(
            user: $user,
            routeIdentifier: $this->routeIdentifier($route),
            sourceStation: $firstLeg->sourceStation(),
            targetStation: $firstLeg->destinationStation(),
            commodityName: $firstLeg->tradeOffer()->commodityName(),
            quantity: $firstLeg->tradeOffer()->quantity(),
            expectedProfitPerHour: (int) round($route->creditsPerHour()),
        );
        $this->snapshots->save($snapshot);
        $this->entityManager->flush();

        return $this->json(['route' => $isMultiLeg ? $this->serializeMultiLegRoute($route, $multiLegCalculator) : $this->serializeSingleLegRoute($route)]);
    }

    /** @param array<string, mixed> $payload */
    private function isMultiLegRequest(array $payload): bool
    {
        if (isset($payload['multiLeg'])) {
            if (!is_bool($payload['multiLeg'])) {
                throw new \InvalidArgumentException('multiLeg must be a boolean.');
            }

            return $payload['multiLeg'];
        }

        return array_key_exists('routeMode', $payload)
            || array_key_exists('maxTotalJumps', $payload)
            || array_key_exists('maxTradeStops', $payload)
            || array_key_exists('maxStops', $payload)
            || array_key_exists('returnToStart', $payload)
            || array_key_exists('closedRoute', $payload)
            || array_key_exists('openRoute', $payload);
    }

    /** @param array<string, mixed> $payload */
    private function returnToStart(array $payload): bool
    {
        if (isset($payload['routeMode'])) {
            if (!is_string($payload['routeMode']) || !in_array($payload['routeMode'], ['open', 'closed'], true)) {
                throw new \InvalidArgumentException('routeMode must be open or closed.');
            }

            return $payload['routeMode'] === 'closed';
        }

        foreach (['returnToStart', 'closedRoute', 'openRoute'] as $field) {
            if (array_key_exists($field, $payload)) {
                if (!is_bool($payload[$field])) {
                    throw new \InvalidArgumentException(sprintf('%s must be a boolean.', $field));
                }

                return $field === 'openRoute' ? !$payload[$field] : $payload[$field];
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function serializeSingleLegRoute(TradeRoute $route): array
    {
        return [
            ...$this->serializeLeg($route),
            'profitCredits' => $route->netProfitCredits(),
            'creditsPerHour' => $route->creditsPerHour(),
            'jumps' => $route->jumpCount(),
            'distance' => $route->systemDistance(),
            'estimatedDurationSeconds' => $route->estimatedDurationSeconds(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeMultiLegRoute(MultiLegRoute $route, MultiStopRouteCalculator $calculator): array
    {
        $active = $calculator->selectActiveRoute($route);
        $legs = array_map(fn (MultiLegRouteLeg $leg): array => $this->serializeLeg($leg->tradeRoute()), $route->legs());

        return [
            'closed' => $route->isClosed(),
            'returnsToStart' => $route->returnsToStart(),
            'profitCredits' => $route->netProfitCredits(),
            'creditsPerHour' => $route->creditsPerHour(),
            'jumps' => $route->totalJumpCount(),
            'tradeStops' => $route->totalStopCount(),
            'estimatedDurationSeconds' => $route->estimatedDurationSeconds(),
            'legs' => $legs,
            'currentLeg' => $this->serializeLeg($active->currentLeg()->tradeRoute()),
            'nextStops' => array_map(static fn ($station): array => [
                'system' => $station->getSystem()->getName(),
                'station' => $station->getName(),
            ], $active->nextStops(3)),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeLeg(TradeRoute $route): array
    {
        return [
            'sourceSystem' => $route->sourceStation()->getSystem()->getName(),
            'sourceStation' => $route->sourceStation()->getName(),
            'destinationSystem' => $route->destinationStation()->getSystem()->getName(),
            'destinationStation' => $route->destinationStation()->getName(),
            'commodity' => $route->tradeOffer()->commodityName(),
            'quantity' => $route->tradeOffer()->quantity(),
            'jumps' => $route->jumpCount(),
            'distance' => $route->systemDistance(),
            'profitCredits' => $route->netProfitCredits(),
            'estimatedDurationSeconds' => $route->estimatedDurationSeconds(),
        ];
    }

    private function routeIdentifier(MultiLegRoute|TradeRoute $route): string
    {
        if ($route instanceof MultiLegRoute) {
            return implode('->', array_map(static fn (MultiLegRouteLeg $leg): string => $leg->destinationStation()->getId(), $route->legs()));
        }

        return $route->sourceStation()->getId().'->'.$route->destinationStation()->getId().':'.$route->tradeOffer()->commodityName();
    }

    /** @param array<string, mixed> $payload */
    private function number(array $payload, string $field): float
    {
        $value = $payload[$field] ?? null;
        if (!is_int($value) && !is_float($value)) {
            throw new \InvalidArgumentException(sprintf('%s must be numeric.', $field));
        }

        return (float) $value;
    }

    /** @param array<string, mixed> $payload */
    private function integer(array $payload, string $field): int
    {
        $value = $payload[$field] ?? null;
        if (!is_int($value)) {
            throw new \InvalidArgumentException(sprintf('%s must be an integer.', $field));
        }

        return $value;
    }
}
