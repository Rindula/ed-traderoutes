<?php

namespace App\Domain\Route;

use App\Domain\Cargo\CargoManifest;
use App\Entity\MarketObservation;
use App\Entity\Station;
use App\Entity\System;
use App\Repository\SystemRepository;

/**
 * Plans bounded open or closed routes by walking executable trade edges.
 *
 * Systems and stations are intentionally not de-duplicated: a repeated visit
 * can be profitable, and the cargo manifest is copied for every branch so a
 * candidate never mutates the state of another candidate.
 */
final class MultiLegRouteCalculator
{
    public function __construct(private readonly ?SystemRepository $systemRepository = null)
    {
    }

    public function selectActiveRoute(MultiLegRoute $route, int $currentLeg = 0): ActiveRoutePreview
    {
        return new ActiveRoutePreview($route, $currentLeg);
    }

    /**
     * @param iterable<Station> $stations
     * @param iterable<MarketObservation> $observations
     */
    public function calculateBest(
        MultiLegRouteRequest $request,
        iterable $stations,
        iterable $observations,
        iterable $catalogSystems = [],
    ): ?MultiLegRoute {
        return $this->calculateCandidates($request, $stations, $observations, $catalogSystems)[0] ?? null;
    }

    /**
     * @param iterable<Station> $stations
     * @param iterable<MarketObservation> $observations
     * @return list<MultiLegRoute>
     */
    public function calculateCandidates(
        MultiLegRouteRequest $request,
        iterable $stations,
        iterable $observations,
        iterable $catalogSystems = [],
    ): array {
        $stationList = [];
        foreach ($stations as $station) {
            if ($station instanceof Station) {
                $stationList[] = $station;
            }
        }

        $eligibleStations = $this->eligibleStations($request->legRequest(), $stationList);
        $jumpSystems = $this->accessibleSystems($stationList, $catalogSystems);
        $latestObservations = $this->latestFreshObservations($request->legRequest(), $observations);
        $originStations = array_values(array_filter(
            $eligibleStations,
            fn (Station $station): bool => $station->getSystem()->getId() === $request->legRequest()->originSystem()->getId(),
        ));

        if (!$request->legRequest()->originSystem()->isAccessible() || $originStations === []) {
            return [];
        }

        $routes = [];
        foreach ($originStations as $originStation) {
            $this->walk(
                request: $request,
                eligibleStations: $eligibleStations,
                jumpSystems: $jumpSystems,
                latestObservations: $latestObservations,
                currentStation: $originStation,
                cargo: new CargoManifest($request->legRequest()->cargoCapacity()),
                legs: [],
                routes: $routes,
            );
        }

        usort($routes, fn (MultiLegRoute $left, MultiLegRoute $right): int => $this->compareRoutes($left, $right));

        return $routes;
    }

    /**
     * @param array<string, Station> $eligibleStations
     * @param array<string, array<string, MarketObservation>> $latestObservations
     * @param list<MultiLegRouteLeg> $legs
     * @param list<MultiLegRoute> $routes
     */
    private function walk(
        MultiLegRouteRequest $request,
        array $eligibleStations,
        array $jumpSystems,
        array $latestObservations,
        Station $currentStation,
        CargoManifest $cargo,
        array $legs,
        array &$routes,
    ): void {
        if (count($legs) >= $request->maxTradeStops() || array_sum(array_map(
            static fn (MultiLegRouteLeg $leg): int => $leg->jumpCount(),
            $legs,
        )) >= $request->maxTotalJumps()) {
            return;
        }

        foreach ($eligibleStations as $destinationStation) {
            if ($destinationStation->getSystem()->getId() === $currentStation->getSystem()->getId()) {
                continue;
            }

            $distance = $this->systemDistance($currentStation, $destinationStation);
            $jumpPath = $this->findJumpPath(
                $currentStation->getSystem(),
                $destinationStation->getSystem(),
                $jumpSystems,
                $request->legRequest()->effectiveJumpDistance(),
            );
            if ($distance === null || $jumpPath === null) {
                continue;
            }

            $jumpCount = count($jumpPath) - 1;
            $jumpsSoFar = array_sum(array_map(
                static fn (MultiLegRouteLeg $leg): int => $leg->jumpCount(),
                $legs,
            ));
            if ($jumpsSoFar + $jumpCount > $request->maxTotalJumps()) {
                continue;
            }

            $cargoBefore = clone $cargo;
            $this->settleCargoAtStation(
                $cargo,
                $latestObservations[$currentStation->getId()] ?? [],
            );

            [$offers, $cargoAfterPurchase] = $this->allocateCargo(
                $request->legRequest(),
                $latestObservations[$currentStation->getId()] ?? [],
                $latestObservations[$destinationStation->getId()] ?? [],
                $cargo,
                $currentStation->getName(),
            );
            if ($offers === []) {
                $cargo = $cargoBefore;
                continue;
            }

            $cargo = $cargoAfterPurchase;

            $tradeRoute = new TradeRoute(
                $currentStation,
                $destinationStation,
                $offers,
                $distance,
                $jumpCount,
                $this->estimatedDurationSeconds($request->legRequest(), $currentStation, $destinationStation, $jumpCount),
            );
            $nextLegs = [...$legs, new MultiLegRouteLeg(
                $tradeRoute,
                $cargoBefore,
                $cargoAfterPurchase,
                $cargoAfterPurchase,
                $jumpPath,
            )];
            $atStart = $destinationStation->getSystem()->getId() === $request->legRequest()->originSystem()->getId();

            if (!$request->returnsToStart() && $nextLegs !== []) {
                $routes[] = new MultiLegRoute($nextLegs, $cargo, false);
            } elseif ($request->returnsToStart() && count($nextLegs) >= 2 && $atStart) {
                $cargoAfterReturn = clone $cargo;
                $this->settleCargoAtStation(
                    $cargoAfterReturn,
                    $latestObservations[$destinationStation->getId()] ?? [],
                );
                $routes[] = new MultiLegRoute($nextLegs, $cargoAfterReturn, true);
            }

            $canContinue = !$request->returnsToStart() || !$atStart;
            if ($canContinue) {
                $this->walk($request, $eligibleStations, $jumpSystems, $latestObservations, $destinationStation, $cargo, $nextLegs, $routes);
            }

            $cargo = $cargoBefore;
        }
    }

    /** @param iterable<Station> $stations @return array<string, Station> */
    private function eligibleStations(RouteCalculationRequest $request, iterable $stations): array
    {
        $eligible = [];
        foreach ($stations as $station) {
            if (!$station instanceof Station || !$request->acceptsStation($station) || !$station->getSystem()->isAccessible()) {
                continue;
            }

            $eligible[$station->getId()] = $station;
        }

        return $eligible;
    }

    /** @param iterable<Station> $stations @param iterable<System> $catalogSystems @return array<string, System> */
    private function accessibleSystems(iterable $stations, iterable $catalogSystems): array
    {
        $systems = [];
        foreach ([...($this->systemRepository?->findAccessible() ?? []), ...[...$catalogSystems]] as $system) {
            if ($system instanceof System && $system->isAccessible()) {
                $systems[$system->getId()] = $system;
            }
        }

        foreach ($stations as $station) {
            if (!$station instanceof Station || !$station->getSystem()->isAccessible()) {
                continue;
            }

            $systems[$station->getSystem()->getId()] = $station->getSystem();
        }

        return $systems;
    }

    /**
     * Find the shortest path through catalogued systems where every jump is
     * within the configured effective range.
     *
     * @param array<string, System> $systems
     * @return list<System>|null
     */
    private function findJumpPath(System $source, System $destination, array $systems, float $range): ?array
    {
        if ($source->getId() === $destination->getId()) {
            return [];
        }

        if (!isset($systems[$source->getId()], $systems[$destination->getId()])) {
            return null;
        }

        $queue = [[$source, [$source]]];
        $visited = [$source->getId() => true];
        while ($queue !== []) {
            [$current, $path] = array_shift($queue);
            foreach ($systems as $candidate) {
                if (isset($visited[$candidate->getId()])) {
                    continue;
                }

                $distance = $this->systemDistanceBetween($current, $candidate);
                if ($distance === null || $distance > $range) {
                    continue;
                }

                $nextPath = [...$path, $candidate];
                if ($candidate->getId() === $destination->getId()) {
                    return $nextPath;
                }

                $visited[$candidate->getId()] = true;
                $queue[] = [$candidate, $nextPath];
            }
        }

        return null;
    }

    /**
     * Allocate the available hold to the most profitable executable offers.
     *
     *
     * @param array<string, MarketObservation> $sourceMarkets
     * @param array<string, MarketObservation> $destinationMarkets
     * @return array{0: list<TradeOffer>, 1: CargoManifest}
     */
    private function allocateCargo(
        RouteCalculationRequest $request,
        array $sourceMarkets,
        array $destinationMarkets,
        CargoManifest $cargoBeforePurchase,
        string $purchaseStation,
    ): array
    {
        $cargo = clone $cargoBeforePurchase;
        if ($cargo->remainingCapacity() < 1) {
            return [[], $cargo];
        }

        $offers = [];
        foreach ($sourceMarkets as $commodityName => $sourceObservation) {
            if (!$this->acceptsCommodity($request, $commodityName)) {
                continue;
            }
            $destinationObservation = $destinationMarkets[$commodityName] ?? null;
            if (!$destinationObservation instanceof MarketObservation) {
                continue;
            }

            $offer = TradeOffer::fromObservations($sourceObservation, $destinationObservation, $cargo->remainingCapacity());
            if ($offer !== null) {
                $offers[] = $offer;
            }
        }

        usort($offers, static fn (TradeOffer $left, TradeOffer $right): int =>
            ($right->profitPerUnit() <=> $left->profitPerUnit())
            ?: ($right->totalProfitCredits() <=> $left->totalProfitCredits())
            ?: ($left->commodityName() <=> $right->commodityName())
        );

        $allocatedOffers = [];
        foreach ($offers as $offer) {
            $quantity = min($offer->quantity(), $cargo->remainingCapacity());
            if ($quantity < 1) {
                break;
            }

            $allocatedOffer = $offer->withQuantity($quantity);
            $cargo->buy(
                $offer->commodityName(),
                $quantity,
                $offer->buyPrice(),
                $purchaseStation,
            );
            $allocatedOffers[] = $allocatedOffer;
        }

        return [$allocatedOffers, $cargo];
    }

    /**
     * Sell cargo carried into the current station before buying the next leg.
     *
     * Demand may only absorb part of a position; any remainder remains loaded
     * and consequently reduces the next leg's available capacity.
     *
     * @param array<string, MarketObservation> $stationMarkets
     */
    private function settleCargoAtStation(CargoManifest $cargo, array $stationMarkets): void
    {
        foreach ($cargo->positions() as $position) {
            $observation = $stationMarkets[$position->commodityName()] ?? null;
            if (!$observation instanceof MarketObservation || $observation->getSellPrice() === null || $observation->getSellPrice() <= 0) {
                continue;
            }

            $demand = $observation->getDemand();
            if ($demand === null || $demand < 1) {
                continue;
            }

            $cargo->sell(
                $position->commodityName(),
                min($position->quantity(), $demand),
                $observation->getSellPrice(),
            );
        }
    }

    /** @param iterable<MarketObservation> $observations @return array<string, array<string, MarketObservation>> */
    private function latestFreshObservations(RouteCalculationRequest $request, iterable $observations): array
    {
        $latest = [];
        foreach ($observations as $observation) {
            if (!$observation instanceof MarketObservation || !$observation->isFreshAt($request->asOf(), $request->maxDataAgeSeconds())) {
                continue;
            }

            $stationId = $observation->getStation()->getId();
            $commodityName = $observation->getCommodityName();
            $current = $latest[$stationId][$commodityName] ?? null;
            if (!$current instanceof MarketObservation || $observation->isNewerThan($current)) {
                $latest[$stationId][$commodityName] = $observation;
            }
        }

        return $latest;
    }

    private function acceptsCommodity(RouteCalculationRequest $request, string $commodityName): bool
    {
        return $request->acceptsCommodity($commodityName);
    }

    private function systemDistance(Station $source, Station $destination): ?float
    {
        return $this->systemDistanceBetween($source->getSystem(), $destination->getSystem());
    }

    private function systemDistanceBetween(System $sourceSystem, System $destinationSystem): ?float
    {
        $sourceCoordinates = [$sourceSystem->getX(), $sourceSystem->getY(), $sourceSystem->getZ()];
        $destinationCoordinates = [$destinationSystem->getX(), $destinationSystem->getY(), $destinationSystem->getZ()];
        if (in_array(null, $sourceCoordinates, true) || in_array(null, $destinationCoordinates, true)) {
            return null;
        }

        $distance = 0.0;
        foreach ([0, 1, 2] as $axis) {
            $delta = $sourceCoordinates[$axis] - $destinationCoordinates[$axis];
            $distance += $delta * $delta;
        }

        $distance = sqrt($distance);

        return $distance > 0.0 ? $distance : null;
    }

    private function estimatedDurationSeconds(RouteCalculationRequest $request, Station $source, Station $destination, int $jumpCount): float
    {
        $times = $request->timeEstimates();

        return ($jumpCount * $times->secondsPerJump())
            + $times->secondsFor($source)
            + $times->secondsFor($destination)
            + $times->secondsPerTrade();
    }

    private function compareRoutes(MultiLegRoute $left, MultiLegRoute $right): int
    {
        $byHourlyProfit = $right->creditsPerHour() <=> $left->creditsPerHour();
        if ($byHourlyProfit !== 0) {
            return $byHourlyProfit;
        }

        $byProfit = $right->netProfitCredits() <=> $left->netProfitCredits();
        if ($byProfit !== 0) {
            return $byProfit;
        }

        return ($left->totalTradeStops() <=> $right->totalTradeStops())
            ?: ($left->totalJumps() <=> $right->totalJumps());
    }
}
