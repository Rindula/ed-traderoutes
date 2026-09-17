<?php

namespace App\Tests\Route;

use App\Domain\Route\RouteCalculationRequest;
use App\Domain\Route\RouteCalculator;
use App\Domain\Route\RouteTimeEstimates;
use App\Domain\Route\TradeRoute;
use App\Entity\MarketObservation;
use App\Entity\Station;
use App\Entity\System;
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

        $route = $this->calculator()->calculateBest(
            $this->request($sol, cargoCapacity: 40, maxJumpDistance: 10.0),
            [$solStation, $achenarStation],
            $observations,
        );

        self::assertInstanceOf(TradeRoute::class, $route);
        self::assertSame('Sol', $route->sourceStation()->getSystem()->getName());
        self::assertSame('Achenar', $route->destinationStation()->getSystem()->getName());
        self::assertSame('Gold', $route->tradeOffer()->commodityName());
        self::assertSame(40, $route->tradeOffer()->quantity());
        self::assertSame(6_000, $route->netProfitCredits());
    }

    public function testItExcludesAPlanWhenOneHyperspaceJumpExceedsTheConfiguredDistance(): void
    {
        [$sol, $solStation, $achenar, $achenarStation] = $this->stations(targetDistance: 11.0);
        $observations = [
            $this->market($solStation, 100, 0, 100, 0),
            $this->market($achenarStation, 0, 250, 0, 100),
        ];

        $route = $this->calculator()->calculateBest(
            $this->request($sol, cargoCapacity: 40, maxJumpDistance: 10.0),
            [$solStation, $achenarStation],
            $observations,
        );

        self::assertNull($route);
    }

    public function testItExcludesAnInaccessibleTargetSystem(): void
    {
        [$sol, $solStation, $achenar, $achenarStation] = $this->stations(targetAccessible: false);
        $observations = [
            $this->market($solStation, 100, 0, 100, 0),
            $this->market($achenarStation, 0, 250, 0, 100),
        ];

        $route = $this->calculator()->calculateBest(
            $this->request($sol, cargoCapacity: 40, maxJumpDistance: 10.0),
            [$solStation, $achenarStation],
            $observations,
        );

        self::assertNull($route);
    }

    public function testItExcludesAStaleMarketObservation(): void
    {
        [$sol, $solStation, $achenar, $achenarStation] = $this->stations();
        $observations = [
            $this->market($solStation, 100, 0, 100, 0, observedAt: '2026-09-17T09:59:59+00:00'),
            $this->market($achenarStation, 0, 250, 0, 100),
        ];

        $route = $this->calculator()->calculateBest(
            $this->request($sol, cargoCapacity: 40, maxJumpDistance: 10.0, maxAgeSeconds: 7200),
            [$solStation, $achenarStation],
            $observations,
        );

        self::assertNull($route);
    }

    /**
     * Unknown or zero supply/demand must not be treated as unlimited. The two
     * cases are kept in one test because both are the same eligibility
     * invariant at the route boundary; positive values below ship capacity are
     * valid partial quantities and are covered by the cargo-capacity test.
     */
    public function testItExcludesTradesWithInsufficientSupplyOrDemand(): void
    {
        [$sol, $solStation, $achenar, $achenarStation] = $this->stations();
        $supplyUnavailable = [
            $this->market($solStation, 100, 0, 0, 0),
            $this->market($achenarStation, 0, 250, 0, 100),
        ];
        $demandUnavailable = [
            $this->market($solStation, 100, 0, 100, 0),
            $this->market($achenarStation, 0, 250, 0, 0),
        ];

        foreach ([$supplyUnavailable, $demandUnavailable] as $observations) {
            $route = $this->calculator()->calculateBest(
                $this->request($sol, cargoCapacity: 40, maxJumpDistance: 10.0),
                [$solStation, $achenarStation],
                $observations,
            );

            self::assertNull($route);
        }
    }

    public function testItCapsTheCargoPlanAtTheShipCapacity(): void
    {
        [$sol, $solStation, $achenar, $achenarStation] = $this->stations();
        $observations = [
            $this->market($solStation, 100, 0, 100, 0),
            $this->market($achenarStation, 0, 250, 0, 100),
        ];

        $route = $this->calculator()->calculateBest(
            $this->request($sol, cargoCapacity: 25, maxJumpDistance: 10.0),
            [$solStation, $achenarStation],
            $observations,
        );

        self::assertInstanceOf(TradeRoute::class, $route);
        self::assertSame(25, $route->tradeOffer()->quantity());
        self::assertSame(3_750, $route->netProfitCredits());
    }

    public function testItRanksByCreditsPerHourAndExposesTheTimeEstimate(): void
    {
        [$sol, $solStation, $achenar, $achenarStation, $lave, $laveStation] = $this->stations();
        $observations = [
            $this->market($solStation, 100, 0, 100, 0),
            $this->market($achenarStation, 0, 200, 0, 100),
            $this->market($laveStation, 0, 150, 0, 100),
        ];

        $route = $this->calculator()->calculateBest(
            $this->request(
                $sol,
                cargoCapacity: 40,
                maxJumpDistance: 10.0,
                timeEstimates: new RouteTimeEstimates(
                    secondsPerJump: 60,
                    secondsPerTrade: 30,
                    defaultStationSeconds: 120,
                ),
            ),
            [$solStation, $achenarStation, $laveStation],
            $observations,
        );

        self::assertInstanceOf(TradeRoute::class, $route);
        self::assertSame('Achenar', $route->destinationStation()->getSystem()->getName());
        self::assertSame(4_000, $route->netProfitCredits());
        self::assertSame(330.0, $route->estimatedDurationSeconds());
        self::assertEqualsWithDelta(43_636.3636, $route->creditsPerHour(), 0.0001);
    }

    private function calculator(): RouteCalculator
    {
        return new RouteCalculator();
    }

    private function request(
        System $startSystem,
        int $cargoCapacity,
        float $maxJumpDistance,
        int $maxAgeSeconds = 7200,
        ?RouteTimeEstimates $timeEstimates = null,
    ): RouteCalculationRequest {
        return new RouteCalculationRequest(
            originSystem: $startSystem,
            shipJumpRange: 10.0,
            maxJumpDistance: $maxJumpDistance,
            cargoCapacity: $cargoCapacity,
            asOf: new DateTimeImmutable(self::CALCULATED_AT),
            maxDataAgeSeconds: $maxAgeSeconds,
            timeEstimates: $timeEstimates,
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
