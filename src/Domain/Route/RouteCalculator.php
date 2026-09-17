<?php

namespace App\Domain\Route;

use App\Entity\MarketObservation;
use App\Entity\Station;

/**
 * Calculates the best single-leg trade from the current system.
 *
 * Persistence and transport stay outside this class: callers provide the catalog
 * and observations, which makes the calculation deterministic and easy to test.
 */
final class RouteCalculator
{
    /**
     * @param iterable<Station> $stations
     * @param iterable<MarketObservation> $observations
     */
    public function calculateBest(RouteCalculationRequest $request, iterable $stations, iterable $observations): ?TradeRoute
    {
        $candidates = $this->calculateCandidates($request, $stations, $observations);

        return $candidates[0] ?? null;
    }

    /**
     * @param iterable<Station> $stations
     * @param iterable<MarketObservation> $observations
     * @return list<TradeRoute>
     */
    public function calculateCandidates(RouteCalculationRequest $request, iterable $stations, iterable $observations): array
    {
        $eligibleStations = [];
        foreach ($stations as $station) {
            if (!$station instanceof Station || !$request->acceptsStation($station)) {
                continue;
            }

            if (!$station->getSystem()->isAccessible()) {
                continue;
            }

            $eligibleStations[$station->getId()] = $station;
        }

        $sourceStations = [];
        $destinationStations = [];
        foreach ($eligibleStations as $station) {
            if ($station->getSystem()->getId() === $request->originSystem()->getId()) {
                $sourceStations[] = $station;
            } else {
                $destinationStations[] = $station;
            }
        }

        if (!$request->originSystem()->isAccessible() || $sourceStations === [] || $destinationStations === []) {
            return [];
        }

        $latestObservations = $this->latestFreshObservations($request, $observations);
        $candidates = [];

        foreach ($sourceStations as $sourceStation) {
            foreach ($destinationStations as $destinationStation) {
                $distance = $this->systemDistance($sourceStation, $destinationStation);
                if ($distance === null || $distance > $request->effectiveJumpDistance()) {
                    continue;
                }

                // Ticket #5 deliberately plans only a direct A -> B leg.
                $jumpCount = 1;
                $sourceMarkets = $latestObservations[$sourceStation->getId()] ?? [];
                $destinationMarkets = $latestObservations[$destinationStation->getId()] ?? [];

                foreach ($sourceMarkets as $commodityName => $sourceObservation) {
                    $destinationObservation = $destinationMarkets[$commodityName] ?? null;
                    if (!$destinationObservation instanceof MarketObservation) {
                        continue;
                    }

                    $offer = TradeOffer::fromObservations(
                        $sourceObservation,
                        $destinationObservation,
                        $request->cargoCapacity(),
                    );
                    if ($offer === null) {
                        continue;
                    }

                    $duration = $this->estimatedDurationSeconds(
                        $request,
                        $sourceStation,
                        $destinationStation,
                        $jumpCount,
                    );
                    $route = new TradeRoute(
                        $sourceStation,
                        $destinationStation,
                        $offer,
                        $distance,
                        $jumpCount,
                        $duration,
                    );

                    $candidates[] = $route;
                }
            }
        }

        usort($candidates, fn (TradeRoute $left, TradeRoute $right): int => $this->compareRoutes($left, $right));

        return $candidates;
    }

    /**
     * @param iterable<MarketObservation> $observations
     * @return array<string, array<string, MarketObservation>>
     */
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

    private function systemDistance(Station $source, Station $destination): ?float
    {
        $sourceSystem = $source->getSystem();
        $destinationSystem = $destination->getSystem();
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

    private function estimatedDurationSeconds(
        RouteCalculationRequest $request,
        Station $source,
        Station $destination,
        int $jumpCount,
    ): float {
        $times = $request->timeEstimates();

        return ($jumpCount * $times->secondsPerJump())
            + $times->secondsFor($source)
            + $times->secondsFor($destination)
            + $times->secondsPerTrade();
    }

    private function compareRoutes(TradeRoute $left, TradeRoute $right): int
    {
        $byHourlyProfit = $right->creditsPerHour() <=> $left->creditsPerHour();
        if ($byHourlyProfit !== 0) {
            return $byHourlyProfit;
        }

        $byProfit = $right->netProfitCredits() <=> $left->netProfitCredits();
        if ($byProfit !== 0) {
            return $byProfit;
        }

        $byDistance = $left->systemDistance() <=> $right->systemDistance();
        if ($byDistance !== 0) {
            return $byDistance;
        }

        return ($left->sourceStation()->getId().'|'.$left->destinationStation()->getId())
            <=> ($right->sourceStation()->getId().'|'.$right->destinationStation()->getId());
    }
}
