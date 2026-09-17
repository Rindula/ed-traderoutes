<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use App\Infrastructure\RedisHealthChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class StatusController extends AbstractController
{
    #[Route('/status', name: 'status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $user = $this->getUser();
        return $this->json(['user' => [
            'id' => method_exists($user, 'getId') ? $user->getId() : null,
            'subject' => $user?->getUserIdentifier(),
            'displayName' => method_exists($user, 'getDisplayName') ? $user->getDisplayName() : null,
        ], 'plugin' => ['status' => 'inaktiv'], 'mode' => 'manuell']);
    }

    #[Route('/health/live', name: 'health_live', methods: ['GET'])]
    public function live(): JsonResponse { return $this->json(['status' => 'ok']); }

    #[Route('/health/ready', name: 'health_ready', methods: ['GET'])]
    public function ready(Connection $connection, RedisHealthChecker $redis): JsonResponse
    {
        try { $connection->executeQuery('SELECT 1')->fetchOne(); }
        catch (\Throwable) { return $this->json(['status' => 'not_ready', 'dependency' => 'postgresql'], 503); }
        if (!$redis->isAvailable()) return $this->json(['status' => 'not_ready', 'dependency' => 'redis'], 503);
        return $this->json(['status' => 'ready', 'dependencies' => ['postgresql' => 'ok', 'redis' => 'ok']]);
    }
}
