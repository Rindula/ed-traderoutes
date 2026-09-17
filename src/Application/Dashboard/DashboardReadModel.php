<?php

namespace App\Application\Dashboard;

use App\Entity\ActiveRoute;
use App\Entity\CargoState;
use App\Entity\PluginStatus;
use App\Entity\User;
use App\Repository\ActiveRouteRepository;
use App\Repository\CargoStateRepository;
use App\Repository\PluginStatusRepository;
use App\Repository\RouteSnapshotRepository;

final class DashboardReadModel
{
    public function __construct(
        private readonly ActiveRouteRepository $activeRoutes,
        private readonly CargoStateRepository $cargoStates,
        private readonly PluginStatusRepository $pluginStatuses,
        private readonly RouteSnapshotRepository $snapshots,
    ) {
    }

    /** @return array<string, mixed> */
    public function forUser(User $user, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $activeRoute = $this->activeRoutes->findForUser($user);
        $cargo = $this->cargoStates->findForUser($user);
        $plugin = $this->pluginStatuses->findOneBy(['user' => $user]);
        $snapshot = $this->snapshots->findLatestForUser($user);

        return [
            'user' => [
                'id' => $user->getId(),
                'subject' => $user->getUserIdentifier(),
                'displayName' => $user->getDisplayName(),
            ],
            'activeRoute' => $this->route($activeRoute),
            'metrics' => $this->metrics($activeRoute),
            'plugin' => $this->plugin($plugin, $now),
            'cargo' => $this->cargo($cargo),
            'alternatives' => $activeRoute?->getAlternatives() ?? [],
            'routeCalculatedAt' => $snapshot?->getCalculatedAt()->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed>|null */
    private function route(?ActiveRoute $route): ?array
    {
        if (!$route instanceof ActiveRoute) {
            return null;
        }

        $legs = $route->getLegs();
        return [
            'routeIdentifier' => $route->getRouteIdentifier(),
            'currentLegIndex' => $route->getCurrentLegIndex(),
            'currentLeg' => $route->getCurrentLeg(),
            'nextStops' => array_values(array_map(
                static fn (array $leg): array => [
                    'system' => $leg['destinationSystem'] ?? null,
                    'station' => $leg['destinationStation'] ?? null,
                ],
                array_slice($legs, $route->getCurrentLegIndex() + 1, 3),
            )),
            'bound' => $route->isCurrentLegBound(),
            'completed' => $route->isCompleted(),
            'boundAt' => $route->getBoundAt()?->format(DATE_ATOM),
            'updatedAt' => $route->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    /** @return array<string, int|float> */
    private function metrics(?ActiveRoute $route): array
    {
        if (!$route instanceof ActiveRoute) {
            return ['profitCredits' => 0, 'creditsPerHour' => 0.0, 'jumps' => 0, 'tradeStops' => 0, 'estimatedDurationSeconds' => 0.0];
        }

        $profit = 0;
        $duration = 0.0;
        $jumps = 0;
        foreach ($route->getLegs() as $leg) {
            $profit += is_int($leg['profitCredits'] ?? null) ? $leg['profitCredits'] : 0;
            $duration += is_numeric($leg['estimatedDurationSeconds'] ?? null) ? (float) $leg['estimatedDurationSeconds'] : 0.0;
            $jumps += is_int($leg['jumps'] ?? null) ? $leg['jumps'] : 0;
        }

        return [
            'profitCredits' => $profit,
            'creditsPerHour' => $duration > 0.0 ? $profit / ($duration / 3600.0) : 0.0,
            'jumps' => $jumps,
            'tradeStops' => count($route->getLegs()),
            'estimatedDurationSeconds' => $duration,
        ];
    }

    /** @return array<string, mixed> */
    private function plugin(?PluginStatus $plugin, \DateTimeImmutable $now): array
    {
        $active = $plugin?->isAutomaticModeAt($now) ?? false;
        return [
            'mode' => $active ? PluginStatus::MODE_ACTIVE : PluginStatus::MODE_MANUAL,
            'status' => $active ? 'active' : 'inactive',
            'heartbeatFresh' => $plugin?->getLastHeartbeatAt() !== null && $active,
            'lastHeartbeatAt' => $plugin?->getLastHeartbeatAt()?->format(DATE_ATOM),
            'missedHeartbeats' => $plugin?->missedHeartbeatsAt($now) ?? PluginStatus::MISSED_HEARTBEAT_THRESHOLD,
        ];
    }

    /** @return array<string, mixed> */
    private function cargo(?CargoState $cargo): array
    {
        return [
            'items' => $cargo?->getCargo() ?? [],
            'usedCapacity' => $cargo?->getUsedCapacity() ?? 0,
            'capacity' => $cargo?->getCargoCapacity() ?? 0,
            'remainingCapacity' => $cargo?->getRemainingCapacity() ?? 0,
            'uncertain' => $cargo?->isUncertain() ?? false,
            'boundRouteIdentifier' => $cargo?->getBoundRouteIdentifier(),
            'boundLegIdentifier' => $cargo?->getBoundLegIdentifier(),
            'updatedAt' => $cargo?->getUpdatedAt()->format(DATE_ATOM),
        ];
    }
}
