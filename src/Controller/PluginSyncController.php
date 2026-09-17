<?php

namespace App\Controller;

use App\Entity\PluginStatus;
use App\Entity\ActiveRoute;
use App\Entity\CargoState;
use App\Entity\SyncEvent;
use App\Entity\SyncKey;
use App\Repository\ActiveRouteRepository;
use App\Repository\CargoStateRepository;
use App\Repository\PluginStatusRepository;
use App\Repository\SyncEventRepository;
use App\Repository\SyncKeyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class PluginSyncController extends AbstractController
{
    public function __construct(
        private readonly SyncKeyRepository $keys,
        private readonly PluginStatusRepository $statuses,
        private readonly SyncEventRepository $events,
        private readonly ActiveRouteRepository $activeRoutes,
        private readonly CargoStateRepository $cargoStates,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('/api/plugin/heartbeat', name: 'plugin_heartbeat', methods: ['POST'])]
    public function heartbeat(Request $request): JsonResponse
    {
        $key = $this->authenticateKey($request);
        if (!$key instanceof SyncKey) {
            return $this->json(['error' => 'invalid_sync_key'], 401);
        }

        $now = new \DateTimeImmutable();
        $key->markActivity($now);
        $status = $this->statuses->findOrCreateForUser($key->getUser());
        $status->receiveHeartbeat($now);
        $this->entityManager->flush();

        return $this->json([
            'status' => PluginStatus::MODE_ACTIVE,
            'receivedAt' => $now->format(DATE_ATOM),
            'activeRoute' => $this->activeRouteState($key->getUser()),
        ]);
    }

    #[Route('/api/plugin/events', name: 'plugin_events', methods: ['POST'])]
    public function events(Request $request): JsonResponse
    {
        $key = $this->authenticateKey($request);
        if (!$key instanceof SyncKey) {
            return $this->json(['error' => 'invalid_sync_key'], 401);
        }

        try {
            $body = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($body)) {
                throw new \InvalidArgumentException('The event payload must be an object.');
            }
            $externalId = $body['eventId'] ?? null;
            $eventType = $body['eventType'] ?? null;
            $sequence = $body['sequence'] ?? null;
            $sourceTimestamp = $body['sourceTimestamp'] ?? null;
            if (!is_string($externalId) || !is_string($eventType) || !is_int($sequence) || !is_string($sourceTimestamp)) {
                throw new \InvalidArgumentException('eventId, eventType, sequence and sourceTimestamp are required.');
            }
            $sourceTime = new \DateTimeImmutable($sourceTimestamp);
            $payload = $body['payload'] ?? [];
            if (!is_array($payload)) {
                throw new \InvalidArgumentException('payload must be an object.');
            }
        } catch (\Throwable $exception) {
            return $this->json(['error' => 'invalid_event', 'message' => $exception->getMessage()], 422);
        }

        $user = $key->getUser();
        $existing = $this->events->findByIdempotencyIdentity($user, $externalId);
        if ($existing instanceof SyncEvent) {
            $candidate = new SyncEvent($user, $externalId, $sequence, $eventType, $sourceTime, $payload);
            if (!$existing->isEquivalentTo($candidate)) {
                return $this->json(['error' => 'conflicting_event'], 409);
            }
            $key->markActivity();
            $this->entityManager->flush();
            return $this->json([
                'status' => 'duplicate',
                'eventId' => $externalId,
                'activeRoute' => $this->activeRouteState($user),
            ]);
        }

        $event = new SyncEvent($user, $externalId, $sequence, $eventType, $sourceTime, $payload);
        $previous = $this->events->findLatestForUser($user);
        $uncertain = $event->hasUncertainSequenceAfter($previous);
        $this->entityManager->persist($event);
        $key->markActivity();
        $status = $this->statuses->findOrCreateForUser($user);
        $status->receiveHeartbeat();
        $lifecycleUncertain = $this->applyLifecycle($user, $event, $previous, $uncertain);
        $this->entityManager->flush();

        return $this->json([
            'status' => ($uncertain || $lifecycleUncertain) ? 'accepted_uncertain' : 'accepted',
            'eventId' => $externalId,
            'activeRoute' => $this->activeRouteState($user),
        ], 202);
    }

    private function applyLifecycle(
        \App\Entity\User $user,
        SyncEvent $event,
        ?SyncEvent $previous,
        bool $sequenceUncertain,
    ): bool {
        $cargo = $this->cargoStates->findForUser($user);
        $cargoPayload = $this->cargoPayload($event->getPayload());
        $cargoMalformed = $cargoPayload === false;
        $active = $this->activeRoutes->findForUser($user);
        $type = strtolower($event->getEventType());
        $isPurchase = in_array($type, ['buy', 'marketbuy', 'purchase', 'commoditybuy'], true)
            && $this->isBuyAction($event->getPayload());
        $isPurchase = $isPurchase || ($type === '売買' && $this->hasAction($event->getPayload(), ['buy', 'purchase', 'kauf']));
        $isMarket = in_array($type, ['market', 'marketvisit', 'marketvisited'], true);
        $isSale = in_array($type, ['sell', 'marketsell', 'sale', 'commoditysell'], true)
            && $this->isSellAction($event->getPayload());
        $isSale = $isSale || ($type === '売買' && $this->hasAction($event->getPayload(), ['sell', 'sale', 'verkauf']));

        if ($sequenceUncertain || $cargoMalformed) {
            if (!$cargo instanceof CargoState) {
                $cargo = $this->cargoStates->findOrCreateForUser($user, $this->payloadCapacity($event->getPayload()) ?? 0);
            }
            $cargo->markUncertain($event->getSourceTimestamp());
            // Keep a confirmed purchase committed even when its cargo snapshot
            // cannot be trusted. This prevents an unsafe route replacement.
            if ($isPurchase && $active instanceof ActiveRoute && !$active->isCompleted()) {
                $active->bindCurrentLeg($event->getSourceTimestamp());
            }
            return true;
        }

        if ($cargoPayload !== null) {
            if (!$cargo instanceof CargoState) {
                $capacity = $this->payloadCapacity($event->getPayload());
                if ($capacity === null) {
                    return true;
                }
                $cargo = $this->cargoStates->findOrCreateForUser($user, $capacity);
            }

            try {
                $cargo->replaceCargo($cargoPayload, $event->getSourceTimestamp());
                $cargo->confirm($event->getSourceTimestamp());
            } catch (\Throwable) {
                $cargo->markUncertain($event->getSourceTimestamp());
                return true;
            }
        }

        if (!$active instanceof ActiveRoute || $active->isCompleted()) {
            return false;
        }

        if ($isPurchase) {
            $active->bindCurrentLeg($event->getSourceTimestamp());
            if ($cargo instanceof CargoState && !$cargo->isUncertain()) {
                try {
                    $cargo->bindToLeg(
                        $active->getRouteIdentifier(),
                        $this->currentLegIdentifier($active),
                        $event->getSourceTimestamp(),
                    );
                } catch (\Throwable) {
                    $cargo->markUncertain($event->getSourceTimestamp());
                    return true;
                }
            }
            return false;
        }

        if (!$active->isCurrentLegBound() || (!$isMarket && !$isSale)) {
            return false;
        }

        // A market visit or sale only proves this leg after a docking event.
        // The preceding raw event is sufficient for the EDMC journal sequence;
        // a missing/reordered event has already put cargo into fail-safe mode.
        if (!$previous instanceof SyncEvent || strtolower($previous->getEventType()) !== 'docked') {
            return false;
        }
        if (!$this->locationMatchesCurrentLeg($active, $event->getPayload())) {
            return false;
        }

        $boundLegIdentifier = $this->currentLegIdentifier($active);
        $routeIdentifier = $active->getRouteIdentifier();
        $active->completeCurrentLeg($event->getSourceTimestamp());
        if ($cargo instanceof CargoState && $cargo->isBoundToLeg(
            $routeIdentifier,
            $boundLegIdentifier,
        )) {
            $cargo->clearBoundLeg($event->getSourceTimestamp());
        }

        return false;
    }

    /** @return array<string, int>|null|false */
    private function cargoPayload(array $payload): array|null|false
    {
        if (!array_key_exists('cargo', $payload)) {
            return null;
        }
        if (!is_array($payload['cargo'])) {
            return false;
        }

        $cargo = [];
        foreach ($payload['cargo'] as $commodity => $quantity) {
            if (!is_string($commodity) || $commodity === '' || !is_int($quantity) || $quantity < 0) {
                return false;
            }
            if ($quantity > 0) {
                $cargo[$commodity] = $quantity;
            }
        }
        ksort($cargo);
        return $cargo;
    }

    private function payloadCapacity(array $payload): ?int
    {
        $capacity = $payload['cargoCapacity'] ?? null;
        return is_int($capacity) && $capacity >= 0 ? $capacity : null;
    }

    private function isBuyAction(array $payload): bool
    {
        return !isset($payload['action']) || in_array(strtolower((string) $payload['action']), ['buy', 'purchase', 'kauf'], true);
    }

    private function isSellAction(array $payload): bool
    {
        return !isset($payload['action']) || in_array(strtolower((string) $payload['action']), ['sell', 'sale', 'verkauf'], true);
    }

    /** @param list<string> $actions */
    private function hasAction(array $payload, array $actions): bool
    {
        return isset($payload['action']) && in_array(strtolower((string) $payload['action']), $actions, true);
    }

    private function currentLegIdentifier(ActiveRoute $route): string
    {
        $leg = $route->getCurrentLeg() ?? [];
        foreach (['legIdentifier', 'identifier', 'id'] as $key) {
            if (is_string($leg[$key] ?? null) && $leg[$key] !== '') {
                return $leg[$key];
            }
        }
        return 'leg-'.$route->getCurrentLegIndex();
    }

    private function locationMatchesCurrentLeg(ActiveRoute $route, array $payload): bool
    {
        $leg = $route->getCurrentLeg() ?? [];
        $expected = $leg['destinationStation'] ?? $leg['targetStation'] ?? $leg['destination'] ?? null;
        $actual = $payload['station'] ?? $payload['stationName'] ?? null;
        if ($expected === null || $actual === null) {
            return true;
        }
        if (is_array($expected)) {
            $expected = $expected['name'] ?? $expected['stationName'] ?? null;
        }
        if (is_array($actual)) {
            $actual = $actual['name'] ?? $actual['stationName'] ?? null;
        }
        return is_string($expected) && is_string($actual) && $expected === $actual;
    }

    /** @return array<string, mixed>|null */
    private function activeRouteState(\App\Entity\User $user): ?array
    {
        $route = $this->activeRoutes->findForUser($user);
        if (!$route instanceof ActiveRoute) {
            return null;
        }
        $cargo = $this->cargoStates->findForUser($user);
        return [
            'routeIdentifier' => $route->getRouteIdentifier(),
            'currentLegIndex' => $route->getCurrentLegIndex(),
            'currentLeg' => $route->getCurrentLeg(),
            'bound' => $route->isCurrentLegBound(),
            'completed' => $route->isCompleted(),
            'boundAt' => $route->getBoundAt()?->format(DATE_ATOM),
            'updatedAt' => $route->getUpdatedAt()->format(DATE_ATOM),
            'cargo' => $cargo?->getCargo() ?? [],
            'cargoUncertain' => $cargo?->isUncertain() ?? false,
        ];
    }

    private function authenticateKey(Request $request): ?SyncKey
    {
        $identifier = $request->headers->get('X-EDMC-Key-Id', '');
        $token = $request->headers->get('X-EDMC-Key', '');

        return $this->keys->findByIdentifierAndPresentedToken($identifier, $token);
    }
}
