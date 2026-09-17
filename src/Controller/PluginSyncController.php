<?php

namespace App\Controller;

use App\Entity\PluginStatus;
use App\Entity\SyncEvent;
use App\Entity\SyncKey;
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

        return $this->json(['status' => PluginStatus::MODE_ACTIVE, 'receivedAt' => $now->format(DATE_ATOM)]);
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
            return $this->json(['status' => 'duplicate', 'eventId' => $externalId]);
        }

        $event = new SyncEvent($user, $externalId, $sequence, $eventType, $sourceTime, $payload);
        $previous = $this->events->findLatestForUser($user);
        $uncertain = $event->hasUncertainSequenceAfter($previous);
        $this->entityManager->persist($event);
        $key->markActivity();
        $status = $this->statuses->findOrCreateForUser($user);
        $status->receiveHeartbeat();
        $this->entityManager->flush();

        return $this->json(['status' => $uncertain ? 'accepted_uncertain' : 'accepted', 'eventId' => $externalId], 202);
    }

    private function authenticateKey(Request $request): ?SyncKey
    {
        $identifier = $request->headers->get('X-EDMC-Key-Id', '');
        $token = $request->headers->get('X-EDMC-Key', '');

        return $this->keys->findByIdentifierAndPresentedToken($identifier, $token);
    }
}
