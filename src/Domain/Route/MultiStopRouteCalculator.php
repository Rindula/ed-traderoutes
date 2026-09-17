<?php

namespace App\Domain\Route;

use App\Entity\MarketObservation;
use App\Entity\Station;

/**
 * Compatibility-named facade for the multi-stop route domain seam.
 */
final class MultiStopRouteCalculator
{
    public function __construct(private readonly MultiLegRouteCalculator $calculator = new MultiLegRouteCalculator())
    {
    }

    /** @param iterable<Station> $stations @param iterable<MarketObservation> $observations */
    public function calculateBest(
        MultiStopRouteCalculationRequest $request,
        iterable $stations,
        iterable $observations,
    ): ?MultiLegRoute {
        return $this->calculator->calculateBest($request->routeRequest(), $stations, $observations);
    }

    /** @param iterable<Station> $stations @param iterable<MarketObservation> $observations @return list<MultiLegRoute> */
    public function calculateCandidates(
        MultiStopRouteCalculationRequest $request,
        iterable $stations,
        iterable $observations,
    ): array {
        return $this->calculator->calculateCandidates($request->routeRequest(), $stations, $observations);
    }

    public function selectActiveRoute(MultiLegRoute $route, int $currentLeg = 0): ActiveRoutePreview
    {
        return new ActiveRoutePreview($route, $currentLeg);
    }
}
