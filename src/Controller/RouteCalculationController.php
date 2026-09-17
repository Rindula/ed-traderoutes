<?php

namespace App\Controller;

use App\Domain\Route\RouteCalculationRequest;
use App\Domain\Route\RouteCalculator;
use App\Domain\Route\RouteTimeEstimates;
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
            $requestData = new RouteCalculationRequest(
                originSystem: $origin,
                shipJumpRange: $this->number($payload, 'shipJumpRange'),
                maxJumpDistance: $this->number($payload, 'maxJumpDistance'),
                cargoCapacity: $this->integer($payload, 'cargoCapacity'),
                asOf: new \DateTimeImmutable(),
                maxDataAgeSeconds: isset($payload['maxDataAgeSeconds']) ? $this->integer($payload, 'maxDataAgeSeconds') : RouteCalculationRequest::DEFAULT_MAX_DATA_AGE_SECONDS,
                landingClassFilter: isset($payload['landingClass']) && is_string($payload['landingClass']) ? $payload['landingClass'] : null,
                allowedStationTypes: array_values($allowedTypes),
                timeEstimates: new RouteTimeEstimates(
                    secondsPerJump: isset($payload['secondsPerJump']) ? $this->number($payload, 'secondsPerJump') : 45.0,
                    secondsPerTrade: isset($payload['secondsPerTrade']) ? $this->number($payload, 'secondsPerTrade') : 30.0,
                ),
            );
        } catch (\Throwable $exception) {
            return $this->json(['error' => 'invalid_route_request', 'message' => $exception->getMessage()], 422);
        }

        $route = $calculator->calculateBest($requestData, $this->stations->findAccessibleMarkets(), $this->observations->findAll());
        if ($route === null) {
            return $this->json(['route' => null, 'message' => 'No executable trade found.'], 404);
        }

        $snapshot = new RouteSnapshot(
            user: $user,
            routeIdentifier: $route->sourceStation()->getId().'->'.$route->destinationStation()->getId().':'.$route->tradeOffer()->commodityName(),
            sourceStation: $route->sourceStation(),
            targetStation: $route->destinationStation(),
            commodityName: $route->tradeOffer()->commodityName(),
            quantity: $route->tradeOffer()->quantity(),
            expectedProfitPerHour: (int) round($route->creditsPerHour()),
        );
        $this->snapshots->save($snapshot);
        $this->entityManager->flush();

        return $this->json([
            'route' => [
                'sourceSystem' => $route->sourceStation()->getSystem()->getName(),
                'sourceStation' => $route->sourceStation()->getName(),
                'destinationSystem' => $route->destinationStation()->getSystem()->getName(),
                'destinationStation' => $route->destinationStation()->getName(),
                'commodity' => $route->tradeOffer()->commodityName(),
                'quantity' => $route->tradeOffer()->quantity(),
                'profitCredits' => $route->netProfitCredits(),
                'creditsPerHour' => $route->creditsPerHour(),
                'jumps' => $route->jumpCount(),
                'distance' => $route->systemDistance(),
                'estimatedDurationSeconds' => $route->estimatedDurationSeconds(),
            ],
        ]);
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
