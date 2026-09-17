<?php

namespace App\Tests\Route;

use App\Entity\MarketObservation;
use App\Entity\Station;
use App\Entity\System;
use App\Route\RoutePlanner;
use App\Route\RoutePlanningRequest;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Ticket #5 contract tests for the first executable trade route.
 *
 * The route planner is deliberately exercised through its public planning
 * boundary. Fixtures use a fixed calculation time and literal market values
 * so these tests do not depend on the wall clock or database ordering.
 */
final class RoutePlannerTest extends TestCase
{
    private const CALCULATED_AT = '2026-09-17T12:00:00+00:00';

    public function testItPlansAProfitableExecutableAtoBRoute(): void
    {
        [$sol, $solStation, $achenar, $achenarStation] = $this->stations();
        $observations = [
            $this->market($solStation, 100, 0, 100, 0),
            $this->market($achenarStation, 0, 250, 0, 100),
        ];

        $route = $this->planner()->plan(
            $this->request($sol, cargoCapacity: 40, maxJumpDistance: 10.0),
            [$sol, $achenar],
            [$solStation, $achenarStation],
            $observations,
        );

        self::assertSame(['Sol', 'Achenar'], $route->getSystemNames());
        self::assertSame('Gold', $route->getCurrentLeg()->getCommodityName());
        self::assertSame(40, $route->getCurrentLeg()->getQuantity());
        self::assertSame(6_000, $route->getExpectedProfit());
        self::assertTrue($route->isExecutable());
    }

    public function testItExcludesAPlanWhenOneHyperspaceJumpExceedsTheConfiguredDistance(): void
    {
        [$sol, $solStation, $achenar, $achenarStation] = $this->stations(targetDistance: 11.0);
        $observations = [
            $this->market($solStation, 100, 0, 100, 0),
            $this->market($achenarStation, 0, 250, 0, 100),
        ];

        $route = $this->planner()->plan(
            $this->request($sol, cargoCapacity: 40, maxJumpDistance: 10.0),
            [$sol, $achenar],
            [$solStation, $achenarStation],
            $observations,
        );

        self::assertFalse($route->hasRoute());
        self::assertSame('no_executable_route', $route->getRejectionReason());
    }

    public function testItExcludesAnInaccessibleTargetSystem(): void
    {
        [$sol, $solStation, $achenar, $achenarStation] = $this->stations(targetAccessible: false);
        $observations = [
            $this->market($solStation, 100, 0, 100, 0),
            $this->market($achenarStation, 0, 250, 0, 100),
        ];

        $route = $this->planner()->plan(
            $this->request($sol, cargoCapacity: 40, maxJumpDistance: 10.0),
            [$sol, $achenar],
            [$solStation, $achenarStation],
            $observations,
        );

        self::assertFalse($route->hasRoute());
        self::assertSame('no_executable_route', $route->getRejectionReason());
    }

    public function testItExcludesAStaleMarketObservation(): void
    {
        [$sol, $solStation, $achenar, $achenarStation] = $this->stations();
        $observations = [
            $this->market($solStation, 100, 0, 100, 0, observedAt: '2026-09-17T09:59:59+00:00'),
            $this->market($achenarStation, 0, 250, 0, 100),
        ];

        $route = $this->planner()->plan(
            $this->request($sol, cargoCapacity: 40, maxJumpDistance: 10.0, maxAgeSeconds: 7200),
            [$sol, $achenar],
            [$solStation, $achenarStation],
            $observations,
        );

        self::assertFalse($route->hasRoute());
        self::assertSame('no_executable_route', $route->getRejectionReason());
    }

    /**
     * Unknown or insufficient supply/demand must not be treated as unlimited.
     * The two cases are kept in one test because both are the same eligibility
     * invariant at the route boundary.
     */
    public function testItExcludesTradesWithInsufficientSupplyOrDemand(): void
    {
        [$sol, $solStation, $achenar, $achenarStation] = $this->stations();
        $supplyTooSmall = [
            $this->market($solStation, 100, 0, 10, 0),
            $this->market($achenarStation, 0, 250, 0, 100),
        ];
        $demandTooSmall = [
            $this->market($solStation, 100, 0, 100, 0),
            $this->market($achenarStation, 0, 250, 0, 10),
        ];

        foreach ([$supplyTooSmall, $demandTooSmall] as $observations) {
            $route = $this->planner()->plan(
                $this->request($sol, cargoCapacity: 40, maxJumpDistance: 10.0),
                [$sol, $achenar],
                [$solStation, $achenarStation],
                $observations,
            );

            self::assertFalse($route->hasRoute());
            self::assertSame('no_executable_route', $route->getRejectionReason());
        }
    }

    public function testItCapsTheCargoPlanAtTheShipCapacity(): void
    {
        [$sol, $solStation, $achenar, $achenarStation] = $this->stations();
        $observations = [
            $this->market($solStation, 100, 0, 100, 0),
            $this->market($achenarStation, 0, 250, 0, 100),
        ];

        $route = $this->planner()->plan(
            $this->request($sol, cargoCapacity: 25, maxJumpDistance: 10.0),
            [$sol, $achenar],
            [$solStation, $achenarStation],
            $observations,
        );

        self::assertSame(25, $route->getCurrentLeg()->getQuantity());
        self::assertSame(25, $route->getCargoPlan()->getTotalQuantity());
        self::assertSame(3_750, $route->getExpectedProfit());
    }

    public function testItRanksByCreditsPerHourAndExposesTheTimeEstimate(): void
    {
        [$sol, $solStation, $achenar, $achenarStation, $lave, $laveStation] = $this->stations();
        $observations = [
            $this->market($solStation, 100, 0, 100, 0),
            $this->market($achenarStation, 0, 200, 0, 100),
            $this->market($laveStation, 0, 150, 0, 100),
        ];

        $route = $this->planner()->plan(
            $this->request(
                $sol,
                cargoCapacity: 40,
                maxJumpDistance: 10.0,
                averageSecondsPerJump: 60,
                stationSeconds: 120,
                maxStops: 1,
            ),
            [$sol, $achenar, $lave],
            [$solStation, $achenarStation, $laveStation],
            $observations,
        );

        self::assertSame('Achenar', $route->getCurrentLeg()->getDestinationSystemName());
        self::assertSame(4_000, $route->getExpectedProfit());
        self::assertSame(240, $route->getEstimatedDurationSeconds());
        self::assertSame(60_000, $route->getCreditsPerHour());
        self::assertSame('credits_per_hour', $route->getRankingMetric());
    }

    private function planner(): RoutePlanner
    {
        return new RoutePlanner();
    }

    private function request(
        System $startSystem,
        int $cargoCapacity,
        float $maxJumpDistance,
        int $maxAgeSeconds = 7200,
        int $averageSecondsPerJump = 60,
        int $stationSeconds = 120,
        int $maxStops = 1,
    ): RoutePlanningRequest {
        return new RoutePlanningRequest(
            startSystem: $startSystem,
            cargoCapacity: $cargoCapacity,
            maxJumpDistance: $maxJumpDistance,
            maxJumps: 4,
            maxStops: $maxStops,
            maxAgeSeconds: $maxAgeSeconds,
            calculatedAt: new DateTimeImmutable(self::CALCULATED_AT),
            averageSecondsPerJump: $averageSecondsPerJump,
            stationSeconds: $stationSeconds,
        );
    }

    /** @return list<System|Station> */
    private function stations(
        float $targetDistance = 8.0,
        bool $targetAccessible = true,
    ): array {
        $catalogAt = new DateTimeImmutable('2026-09-17T08:00:00+00:00');
        $sol = new System('Sol', 'test-fixture', $catalogAt, 0.0, 0.0, 0.0, true);
        $achenar = new System('Achenar', 'test-fixture', $catalogAt, $targetDistance, 0.0, 0.0, $targetAccessible);
        $lave = new System('Lave', 'test-fixture', $catalogAt, 3.0, 0.0, 0.0, true);

        return [
            $sol,
            new Station($sol, 'Galileo', Station::TYPE_ORBITAL, 'test-fixture', $catalogAt, landingClass: Station::LANDING_LARGE, accessible: true),
            $achenar,
            new Station($achenar, 'Dawes Hub', Station::TYPE_ORBITAL, 'test-fixture', $catalogAt, landingClass: Station::LANDING_LARGE, accessible: $targetAccessible),
            $lave,
            new Station($lave, 'Lave Station', Station::TYPE_ORBITAL, 'test-fixture', $catalogAt, landingClass: Station::LANDING_LARGE, accessible: true),
        ];
    }

    private function market(
        Station $station,
        int $buyPrice,
        int $sellPrice,
        int $stock,
        int $demand,
        string $observedAt = self::CALCULATED_AT,
    ): MarketObservation {
        return new MarketObservation(
            station: $station,
            commodityName: 'Gold',
            source: MarketObservation::SOURCE_EDDN,
            observedAt: new DateTimeImmutable($observedAt),
            buyPrice: $buyPrice > 0 ? $buyPrice : null,
            sellPrice: $sellPrice > 0 ? $sellPrice : null,
            stock: $stock,
            demand: $demand,
            receivedAt: new DateTimeImmutable($observedAt),
        );
    }
}
