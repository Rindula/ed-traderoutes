<?php

namespace App\Tests\Plugin;

use App\Entity\ActiveRoute;
use App\Entity\CargoState;
use App\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ActiveRouteLifecycleTest extends TestCase
{
    public function testPurchaseBindsTheCurrentLegAndDockingAloneDoesNotAdvanceIt(): void
    {
        $at = new DateTimeImmutable('2026-09-17T12:00:00+00:00');
        $route = new ActiveRoute(new User('commander'), 'route-7', [
            ['legIdentifier' => 'leg-a', 'destinationStation' => 'Beta Market'],
            ['legIdentifier' => 'leg-b', 'destinationStation' => 'Gamma Market'],
        ], $at);
        $cargo = new CargoState(new User('cargo-owner'), 32, ['Gold' => 10], updatedAt: $at);

        $route->bindCurrentLeg($at->modify('+1 second'));
        $cargo->bindToLeg('route-7', 'leg-a', $at->modify('+1 second'));

        self::assertTrue($route->isCurrentLegBound());
        self::assertSame(0, $route->getCurrentLegIndex());
        self::assertTrue($cargo->isBoundToLeg('route-7', 'leg-a'));
    }

    public function testDockingAndMarketVisitAdvanceAndReleaseTheBoundLeg(): void
    {
        $at = new DateTimeImmutable('2026-09-17T12:00:00+00:00');
        $route = new ActiveRoute(new User('commander'), 'route-7', [
            ['legIdentifier' => 'leg-a'],
            ['legIdentifier' => 'leg-b'],
        ], $at);
        $route->bindCurrentLeg($at);

        // Docking is an arrival signal; the market visit is the completion signal.
        self::assertSame(0, $route->getCurrentLegIndex());
        $route->completeCurrentLeg($at->modify('+2 minutes'));

        self::assertSame(1, $route->getCurrentLegIndex());
        self::assertFalse($route->isCurrentLegBound());
        self::assertSame('leg-b', $route->getCurrentLeg()['legIdentifier']);
    }

    public function testUncertainCargoRemainsBoundAndCannotBeSilentlyReleased(): void
    {
        $at = new DateTimeImmutable('2026-09-17T12:00:00+00:00');
        $route = new ActiveRoute(new User('commander'), 'route-7', [['legIdentifier' => 'leg-a']], $at);
        $cargo = new CargoState(new User('cargo-owner'), 10, ['Gold' => 10], uncertain: true, updatedAt: $at);
        $route->bindCurrentLeg($at);
        $cargo->bindToLeg('route-7', 'leg-a', $at);

        self::assertTrue($cargo->isUncertain());
        self::assertTrue($route->isCurrentLegBound());
        self::assertTrue($cargo->hasBoundLeg());
    }
}
