<?php

namespace App\Tests\Cargo;

use App\Domain\Cargo\CargoManifest;
use PHPUnit\Framework\TestCase;

/**
 * Ticket #6 contract tests for the stateful cargo boundary.
 *
 * The manifest is exercised through its public state-transition boundary. It
 * is deliberately independent from route search: route planning can carry
 * the same manifest through repeated stations and trade legs.
 */
final class CargoStateTest extends TestCase
{
    public function testAPlanningRunStartsWithAnEmptyHold(): void
    {
        $cargo = new CargoManifest(64);

        self::assertSame(64, $cargo->capacity());
        self::assertSame(0, $cargo->usedCapacity());
        self::assertTrue($cargo->isEmpty());
        self::assertSame([], $cargo->positions());
    }

    public function testCargoCanAllocateCapacityAcrossSeveralCommodities(): void
    {
        $cargo = new CargoManifest(40);

        $cargo->buy('Gold', 25, 100, 'Sol');
        $cargo->buy('Silver', 15, 200, 'Sol');

        self::assertSame(25, $cargo->positionFor('Gold')?->quantity());
        self::assertSame(15, $cargo->positionFor('Silver')?->quantity());
        self::assertSame(40, $cargo->usedCapacity());
    }

    public function testTheCargoCapacityInvariantHoldsForEveryAllocation(): void
    {
        $cargo = new CargoManifest(40);

        $cargo->buy('Gold', 20, 100, 'Sol');
        self::assertSame(20, $cargo->usedCapacity());
        self::assertSame(20, $cargo->remainingCapacity());

        $cargo->buy('Silver', 20, 200, 'Sol');

        self::assertLessThanOrEqual($cargo->capacity(), $cargo->usedCapacity());
        self::assertSame(40, $cargo->usedCapacity());
        self::assertSame(0, $cargo->remainingCapacity());
    }

    public function testAPartialSaleReducesOnlyTheSoldCommodity(): void
    {
        $cargo = new CargoManifest(40);
        $cargo->buy('Gold', 30, 100, 'Sol');
        $cargo->buy('Silver', 10, 200, 'Sol');

        $profit = $cargo->sell('Gold', 12, 150);

        self::assertSame(600, $profit);
        self::assertSame(18, $cargo->positionFor('Gold')?->quantity());
        self::assertSame(10, $cargo->positionFor('Silver')?->quantity());
        self::assertSame(28, $cargo->usedCapacity());
    }

    public function testARepeatedStationVisitKeepsTheRemainingCargoState(): void
    {
        $cargo = new CargoManifest(40);
        $cargo->buy('Gold', 20, 100, 'Sol');
        $cargo->sell('Gold', 8, 150);

        // The route returns to Sol and loads another commodity.  The unsold
        // Gold must remain part of the same state across the revisit.
        $cargo->buy('Silver', 15, 200, 'Sol');

        self::assertSame(12, $cargo->positionFor('Gold')?->quantity());
        self::assertSame(15, $cargo->positionFor('Silver')?->quantity());
        self::assertSame(27, $cargo->usedCapacity());
        self::assertSame('Sol', $cargo->positionFor('Silver')?->lastPurchaseStation());
    }

    public function testAnOverCapacityPurchaseIsRejectedWithoutChangingTheHold(): void
    {
        $cargo = new CargoManifest(40);
        $cargo->buy('Gold', 30, 100, 'Sol');

        try {
            $cargo->buy('Silver', 11, 200, 'Sol');
            self::fail('An over-capacity purchase must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('capacity', strtolower($exception->getMessage()));
        }

        self::assertSame(30, $cargo->usedCapacity());
        self::assertFalse($cargo->hasCommodity('Silver'));
    }
}
