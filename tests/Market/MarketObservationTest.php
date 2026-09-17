<?php

namespace App\Tests\Market;

use App\Entity\MarketObservation;
use App\Entity\Station;
use App\Entity\System;
use PHPUnit\Framework\TestCase;

final class MarketObservationTest extends TestCase
{
    public function testNewestObservationWinsWhenEddnAndEdmcDisagree(): void
    {
        $eddn = $this->observation(
            source: MarketObservation::SOURCE_EDDN,
            observedAt: '2026-09-17T10:00:00+00:00',
            receivedAt: '2026-09-17T10:01:00+00:00',
            buyPrice: 100,
        );
        $edmc = $this->observation(
            source: MarketObservation::SOURCE_EDMC,
            observedAt: '2026-09-17T10:05:00+00:00',
            receivedAt: '2026-09-17T10:06:00+00:00',
            buyPrice: 125,
        );

        self::assertTrue($edmc->isNewerThan($eddn));
        self::assertFalse($eddn->isNewerThan($edmc));
        self::assertSame(125, $edmc->getBuyPrice());
        self::assertSame(MarketObservation::SOURCE_EDMC, $edmc->getSource());
    }

    public function testNewestObservationWinsWhenEdmcArrivesBeforeEddn(): void
    {
        $edmc = $this->observation(
            source: MarketObservation::SOURCE_EDMC,
            observedAt: '2026-09-17T10:00:00+00:00',
            receivedAt: '2026-09-17T10:01:00+00:00',
            buyPrice: 100,
        );
        $eddn = $this->observation(
            source: MarketObservation::SOURCE_EDDN,
            observedAt: '2026-09-17T10:05:00+00:00',
            receivedAt: '2026-09-17T10:06:00+00:00',
            buyPrice: 125,
        );

        self::assertTrue($eddn->isNewerThan($edmc));
        self::assertFalse($edmc->isNewerThan($eddn));
        self::assertSame(125, $eddn->getBuyPrice());
        self::assertSame(MarketObservation::SOURCE_EDDN, $eddn->getSource());
    }

    public function testOlderObservationIsRejectedAsCurrentObservation(): void
    {
        $current = $this->observation(
            source: MarketObservation::SOURCE_EDMC,
            observedAt: '2026-09-17T10:05:00+00:00',
            receivedAt: '2026-09-17T10:06:00+00:00',
            buyPrice: 125,
        );
        $older = $this->observation(
            source: MarketObservation::SOURCE_EDDN,
            observedAt: '2026-09-17T10:00:00+00:00',
            receivedAt: '2026-09-17T10:07:00+00:00',
            buyPrice: 100,
        );

        self::assertFalse($older->isNewerThan($current));
        self::assertSame(125, $current->getBuyPrice());
        self::assertSame(MarketObservation::SOURCE_EDMC, $current->getSource());
    }

    public function testStaleObservationIsRejectedOutsideTheConfiguredAgeWindow(): void
    {
        $stale = $this->observation(
            source: MarketObservation::SOURCE_EDDN,
            observedAt: '2026-09-17T09:59:59+00:00',
            receivedAt: '2026-09-17T10:00:00+00:00',
            buyPrice: 100,
        );

        self::assertFalse($stale->isFreshAt(
            new \DateTimeImmutable('2026-09-17T12:00:00+00:00'),
            7200,
        ));
    }

    public function testSharedObservationContainsMarketDataWithoutUserIdentity(): void
    {
        $observation = $this->observation(
            source: MarketObservation::SOURCE_EDMC,
            observedAt: '2026-09-17T10:05:00+00:00',
            receivedAt: '2026-09-17T10:06:00+00:00',
            buyPrice: 125,
            sellPrice: 115,
            stock: 40,
            demand: 20,
        );

        $sharedObservation = [
            'system' => $observation->getStation()->getSystem()->getName(),
            'station' => $observation->getStation()->getName(),
            'commodity' => $observation->getCommodityName(),
            'buyPrice' => $observation->getBuyPrice(),
            'sellPrice' => $observation->getSellPrice(),
            'stock' => $observation->getStock(),
            'demand' => $observation->getDemand(),
            'source' => $observation->getSource(),
            'observedAt' => $observation->getObservedAt()->format(DATE_ATOM),
        ];

        self::assertSame([
            'system' => 'Sol',
            'station' => 'Galileo',
            'commodity' => 'Gold',
            'buyPrice' => 125,
            'sellPrice' => 115,
            'stock' => 40,
            'demand' => 20,
            'source' => MarketObservation::SOURCE_EDMC,
            'observedAt' => '2026-09-17T10:05:00+00:00',
        ], $sharedObservation);
        self::assertArrayNotHasKey('userId', $sharedObservation);
        self::assertArrayNotHasKey('commanderName', $sharedObservation);
    }

    private function observation(
        string $source,
        string $observedAt,
        string $receivedAt,
        int $buyPrice,
        ?int $sellPrice = null,
        ?int $stock = null,
        ?int $demand = null,
    ): MarketObservation {
        $system = new System(
            name: 'Sol',
            catalogSource: 'test-fixture',
            catalogObservedAt: new \DateTimeImmutable('2026-09-17T09:00:00+00:00'),
            accessible: true,
        );
        $station = new Station(
            system: $system,
            name: 'Galileo',
            stationType: Station::TYPE_ORBITAL,
            catalogSource: 'test-fixture',
            catalogObservedAt: new \DateTimeImmutable('2026-09-17T09:00:00+00:00'),
            landingClass: Station::LANDING_LARGE,
            accessible: true,
        );

        return new MarketObservation(
            station: $station,
            commodityName: 'Gold',
            source: $source,
            observedAt: new \DateTimeImmutable($observedAt),
            buyPrice: $buyPrice,
            sellPrice: $sellPrice,
            stock: $stock,
            demand: $demand,
            receivedAt: new \DateTimeImmutable($receivedAt),
        );
    }
}
