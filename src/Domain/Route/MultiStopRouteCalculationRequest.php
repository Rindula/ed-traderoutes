<?php

namespace App\Domain\Route;

use App\Entity\System;

/**
 * Public request shape for the multi-stop planner.
 *
 * It keeps the single-leg filters in the same request vocabulary while adding
 * independent route-length and closure constraints.
 */
final readonly class MultiStopRouteCalculationRequest
{
    private RouteCalculationRequest $legRequest;

    public function __construct(
        System $originSystem,
        float $shipJumpRange,
        float $maxJumpDistance,
        int $cargoCapacity,
        \DateTimeImmutable $asOf,
        int $maxTotalJumps,
        int $maxStops,
        bool $returnToStart = false,
        int $maxDataAgeSeconds = RouteCalculationRequest::DEFAULT_MAX_DATA_AGE_SECONDS,
        ?string $landingClassFilter = null,
        array $allowedStationTypes = [],
        array $illegalCommodities = [],
        bool $allowIllegalCommodities = false,
        ?RouteTimeEstimates $timeEstimates = null,
    ) {
        $this->legRequest = new RouteCalculationRequest(
            originSystem: $originSystem,
            shipJumpRange: $shipJumpRange,
            maxJumpDistance: $maxJumpDistance,
            cargoCapacity: $cargoCapacity,
            asOf: $asOf,
            maxDataAgeSeconds: $maxDataAgeSeconds,
            landingClassFilter: $landingClassFilter,
            allowedStationTypes: $allowedStationTypes,
            illegalCommodities: $illegalCommodities,
            allowIllegalCommodities: $allowIllegalCommodities,
            timeEstimates: $timeEstimates,
        );
        $this->routeRequest = new MultiLegRouteRequest($this->legRequest, $maxTotalJumps, $maxStops, $returnToStart);
    }

    private MultiLegRouteRequest $routeRequest;

    public function routeRequest(): MultiLegRouteRequest
    {
        return $this->routeRequest;
    }

    public function legRequest(): RouteCalculationRequest
    {
        return $this->legRequest;
    }
}
