<?php

namespace App\Tests\Route;

use App\Entity\MarketObservation;
use App\Entity\Station;
use App\Entity\System;
use App\Domain\Route\MultiLegRouteCalculator;
use App\Domain\Route\MultiLegRouteRequest;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Ticket #7 contract tests for stateful, multi-stop route planning.
 *
 * The tests exercise the public multi-leg planner seam. The active-route
 * preview remains a separate contract until its application service exists.
 */
final class MultiStopRoutePlannerTest extends TestCase
{
    private const CALCULATED_AT = '2026-09-17T12:00:00+00:00';

    public function testPlansAnOpenMultiStopAtoBtoCRouteWithinSeparateBudgets(): void
    {
        $this->requireTicket7Planner();
        [$a, $aStation, $b, $bStation, $c, $cStation] = $this->threeStopFixture();

        $route = $this->planner()->calculateBest(
            $this->request($a, maxTotalJumps: 2, maxStops: 2),
            [$aStation, $bStation, $cStation],
            $this->marketsForAtoBtoC($aStation, $bStation, $cStation),
        );

        self::assertNotNull($route);
        self::assertFalse($route->isClosed());
        self::assertSame(2, $route->totalJumps());
        self::assertSame(2, $route->totalTradeStops());
        self::assertSame(['B', 'C'], $this->systemNames(array_map(
            static fn (object $leg): object => $leg->destinationStation(),
            $route->legs(),
        )));
        self::assertSame(['Gold', 'Silver'], array_map(
            static fn (object $leg): string => $leg->tradeRoute()->tradeOffer()->commodityName(),
            $route->legs(),
        ));
    }

    public function testReturnToStartIsOptionalAndCanCloseTheRoute(): void
    {
        $this->requireTicket7Planner();
        [$a, $aStation, $b, $bStation, $c, $cStation] = $this->threeStopFixture();

        $markets = array_merge(
            $this->marketsForAtoBtoC($aStation, $bStation, $cStation),
            [$this->market($cStation, 'Copper', buyPrice: 20, stock: 10)],
            [$this->market($aStation, 'Copper', sellPrice: 100, demand: 10)],
        );

        $openRoute = $this->planner()->calculateBest(
            $this->request($a, maxTotalJumps: 3, maxStops: 3, returnToStart: false),
            [$aStation, $bStation, $cStation],
            $markets,
        );
        $closedRoute = $this->planner()->calculateBest(
            $this->request($a, maxTotalJumps: 3, maxStops: 3, returnToStart: true),
            [$aStation, $bStation, $cStation],
            $markets,
        );

        self::assertNotNull($openRoute);
        self::assertNotNull($closedRoute);
        self::assertFalse($openRoute->isClosed());
        self::assertTrue($closedRoute->isClosed());
        self::assertSame('A', $closedRoute->legs()[2]->destinationStation()->getSystem()->getName());
        self::assertSame(3, $closedRoute->totalTradeStops());
    }

    public function testTotalJumpAndStopLimitsAreIndependent(): void
    {
        $this->requireTicket7Planner();
        [$a, $aStation, $b, $bStation, $c, $cStation] = $this->threeStopFixture();
        $markets = $this->marketsForAtoBtoC($aStation, $bStation, $cStation);

        $tooFewJumps = $this->planner()->calculateBest(
            $this->request($a, maxTotalJumps: 1, maxStops: 2),
            [$aStation, $bStation, $cStation],
            $markets,
        );
        $tooFewStops = $this->planner()->calculateBest(
            $this->request($a, maxTotalJumps: 2, maxStops: 1),
            [$aStation, $bStation, $cStation],
            $markets,
        );
        $withinBoth = $this->planner()->calculateBest(
            $this->request($a, maxTotalJumps: 2, maxStops: 2),
            [$aStation, $bStation, $cStation],
            $markets,
        );

        self::assertNotNull($tooFewJumps);
        self::assertSame(1, $tooFewJumps->totalJumps());
        self::assertSame(1, $tooFewJumps->totalTradeStops());
        self::assertNotNull($tooFewStops);
        self::assertSame(1, $tooFewStops->totalTradeStops());
        self::assertNotNull($withinBoth);
        self::assertSame(2, $withinBoth->totalJumps());
        self::assertSame(2, $withinBoth->totalTradeStops());
    }

    public function testRepeatedStationsRemainValidWhenTheCargoPlanIsExecutable(): void
    {
        $this->requireTicket7Planner();
        [$a, $aStation, $b, $bStation, $c, $cStation] = $this->threeStopFixture();
        $markets = array_merge(
            $this->marketsForAtoBtoC($aStation, $bStation, $cStation),
            [$this->market($cStation, 'Copper', buyPrice: 20, stock: 10)],
            [$this->market($aStation, 'Copper', sellPrice: 100, demand: 10)],
            [$this->market($aStation, 'Gold', buyPrice: 100, stock: 10)],
            [$this->market($bStation, 'Gold', sellPrice: 200, demand: 10)],
        );

        $route = $this->planner()->calculateBest(
            $this->request($a, maxTotalJumps: 4, maxStops: 4, returnToStart: true),
            [$aStation, $bStation, $cStation],
            $markets,
        );

        self::assertNotNull($route);
        $stops = array_map(
            static fn (object $leg): object => $leg->destinationStation(),
            $route->legs(),
        );
        self::assertContains('A', $this->systemNames($stops));
        self::assertGreaterThan(1, count(array_unique($this->systemNames($stops))));
        self::assertLessThanOrEqual(4, $route->totalJumps());
        self::assertLessThanOrEqual(4, $route->totalTradeStops());
    }

    public function testCargoPlanStartsEmptyAndCarriesCargoAcrossMultipleLegs(): void
    {
        $this->requireTicket7Planner();
        [$a, $aStation, $b, $bStation, $c, $cStation] = $this->threeStopFixture();

        $route = $this->planner()->calculateBest(
            $this->request($a, cargoCapacity: 10, maxTotalJumps: 2, maxStops: 2),
            [$aStation, $bStation, $cStation],
            $this->marketsForAtoBtoC($aStation, $bStation, $cStation),
        );

        self::assertNotNull($route);
        self::assertTrue($route->legs()[0]->cargoBefore()->isEmpty());
        self::assertSame(['Gold' => 10], $this->cargoContents($route->legs()[0]->cargoAfter()));
        self::assertSame(['Gold' => 10], $this->cargoContents($route->legs()[1]->cargoBefore()));
        self::assertSame(['Silver' => 10], $this->cargoContents($route->legs()[1]->cargoAfter()));
        self::assertSame(10, $route->legs()[0]->cargoAfter()->capacity());
    }

    public function testActiveRouteSelectionShowsCurrentLegAndTheNextThreeStops(): void
    {
        $this->requireTicket7Planner();
        $previewClass = 'App\\Domain\\Route\\ActiveRoutePreview';
        if (!class_exists($previewClass)) {
            self::markTestIncomplete(sprintf('Ticket #7 active-route preview seam is not available yet: %s.', $previewClass));
        }
        [$a, $aStation, $b, $bStation, $c, $cStation, $d, $dStation, $e, $eStation] = $this->fiveStopFixture();

        $route = $this->planner()->calculateBest(
            $this->request($a, maxTotalJumps: 4, maxStops: 4),
            [$aStation, $bStation, $cStation, $dStation, $eStation],
            $this->marketsForFiveStops($aStation, $bStation, $cStation, $dStation, $eStation),
        );

        self::assertNotNull($route);
        $active = $this->planner()->selectActiveRoute($route, currentLeg: 0);

        self::assertSame('B', $active->currentLeg()->destinationStation()->getSystem()->getName());
        self::assertSame(['C', 'D', 'E'], $this->systemNames($active->nextStops(3)));
        self::assertCount(3, $active->nextStops(3));
    }

    private function requireTicket7Planner(): void
    {
        $required = [
            MultiLegRouteCalculator::class,
            MultiLegRouteRequest::class,
        ];

        foreach ($required as $class) {
            if (!class_exists($class)) {
                self::markTestIncomplete(sprintf('Ticket #7 production seam is not available yet: %s.', $class));
            }
        }
    }

    private function planner(): object
    {
        return new MultiLegRouteCalculator();
    }

    private function request(
        System $origin,
        int $cargoCapacity = 10,
        int $maxTotalJumps = 2,
        int $maxStops = 2,
        bool $returnToStart = false,
    ): object {
        return new MultiLegRouteRequest(
            legRequest: new \App\Domain\Route\RouteCalculationRequest(
                originSystem: $origin,
                shipJumpRange: 4.0,
                maxJumpDistance: 4.0,
                cargoCapacity: $cargoCapacity,
                asOf: new DateTimeImmutable(self::CALCULATED_AT),
            ),
            maxTotalJumps: $maxTotalJumps,
            maxTradeStops: $maxStops,
            returnToStart: $returnToStart,
        );
    }

    /** @return list<System|Station> */
    private function threeStopFixture(): array
    {
        $catalogAt = new DateTimeImmutable('2026-09-17T08:00:00+00:00');
        $a = new System('A', 'test-fixture', $catalogAt, 0.0, 0.0, 0.0, true);
        $b = new System('B', 'test-fixture', $catalogAt, 4.0, 0.0, 0.0, true);
        $c = new System('C', 'test-fixture', $catalogAt, 2.0, 3.0, 0.0, true);

        return [
            $a,
            $this->station($a, 'A Market'),
            $b,
            $this->station($b, 'B Market'),
            $c,
            $this->station($c, 'C Market'),
        ];
    }

    /** @return list<System|Station> */
    private function fiveStopFixture(): array
    {
        $catalogAt = new DateTimeImmutable('2026-09-17T08:00:00+00:00');
        $systems = [];
        $stations = [];
        foreach (['A', 'B', 'C', 'D', 'E'] as $index => $name) {
            $system = new System($name, 'test-fixture', $catalogAt, (float) ($index * 4), 0.0, 0.0, true);
            $systems[] = $system;
            $stations[] = $this->station($system, $name.' Market');
        }

        return [$systems[0], $stations[0], $systems[1], $stations[1], $systems[2], $stations[2], $systems[3], $stations[3], $systems[4], $stations[4]];
    }

    private function station(System $system, string $name): Station
    {
        return new Station(
            system: $system,
            name: $name,
            stationType: Station::TYPE_ORBITAL,
            catalogSource: 'test-fixture',
            catalogObservedAt: new DateTimeImmutable('2026-09-17T08:00:00+00:00'),
            landingClass: Station::LANDING_LARGE,
            accessible: true,
        );
    }

    /** @return list<MarketObservation> */
    private function marketsForAtoBtoC(Station $a, Station $b, Station $c): array
    {
        return [
            $this->market($a, 'Gold', buyPrice: 100, stock: 10),
            $this->market($b, 'Gold', sellPrice: 200, demand: 10),
            $this->market($b, 'Silver', buyPrice: 50, stock: 10),
            $this->market($c, 'Silver', sellPrice: 150, demand: 10),
        ];
    }

    /** @return list<MarketObservation> */
    private function marketsForFiveStops(Station ...$stations): array
    {
        $commodities = ['Gold', 'Silver', 'Platinum', 'Palladium'];
        $markets = [];
        foreach ($commodities as $index => $commodity) {
            $markets[] = $this->market($stations[$index], $commodity, buyPrice: 100, stock: 10);
            $markets[] = $this->market($stations[$index + 1], $commodity, sellPrice: 200, demand: 10);
        }

        return $markets;
    }

    private function market(
        Station $station,
        string $commodity,
        ?int $buyPrice = null,
        ?int $sellPrice = null,
        ?int $stock = null,
        ?int $demand = null,
    ): MarketObservation {
        return new MarketObservation(
            station: $station,
            commodityName: $commodity,
            source: MarketObservation::SOURCE_EDDN,
            observedAt: new DateTimeImmutable(self::CALCULATED_AT),
            buyPrice: $buyPrice,
            sellPrice: $sellPrice,
            stock: $stock,
            demand: $demand,
            receivedAt: new DateTimeImmutable(self::CALCULATED_AT),
        );
    }

    /** @param object $manifest @return array<string, int> */
    private function cargoContents(object $manifest): array
    {
        $cargo = [];
        foreach ($manifest->positions() as $position) {
            $cargo[$position->commodityName()] = $position->quantity();
        }

        ksort($cargo);

        return $cargo;
    }

    /** @param iterable<object> $stations @return list<string> */
    private function systemNames(iterable $stations): array
    {
        return array_map(
            static fn (object $station): string => $station->getSystem()->getName(),
            [...$stations],
        );
    }
}
