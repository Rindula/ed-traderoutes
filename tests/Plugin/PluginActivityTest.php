<?php

namespace App\Tests\Plugin;

use App\Entity\PluginStatus;
use App\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PluginActivityTest extends TestCase
{
    public function testHeartbeatAtTheConfiguredSixtySecondIntervalKeepsPluginActive(): void
    {
        $startedAt = new DateTimeImmutable('2026-09-17T12:00:00+00:00');
        $status = new PluginStatus(new User('authentik-subject'), $startedAt);

        self::assertFalse($status->isHeartbeatExpiredAt($startedAt->modify('+60 seconds')));
        self::assertTrue($status->isAutomaticModeAt($startedAt->modify('+60 seconds')));
    }

    public function testThreeMissedHeartbeatIntervalsSwitchToManualMode(): void
    {
        $startedAt = new DateTimeImmutable('2026-09-17T12:00:00+00:00');
        $status = new PluginStatus(new User('authentik-subject'), $startedAt);
        $expiredAt = $startedAt->modify('+180 seconds');

        $status->refreshModeAt($expiredAt);

        self::assertSame(PluginStatus::MISSED_HEARTBEAT_THRESHOLD, $status->missedHeartbeatsAt($expiredAt));
        self::assertTrue($status->isHeartbeatExpiredAt($expiredAt));
        self::assertTrue($status->isManual());
        self::assertFalse($status->isAutomaticModeAt($expiredAt));
    }

    public function testNextValidHeartbeatRestoresActiveMode(): void
    {
        $startedAt = new DateTimeImmutable('2026-09-17T12:00:00+00:00');
        $status = new PluginStatus(new User('authentik-subject'), $startedAt);
        $status->refreshModeAt($startedAt->modify('+3 minutes'));
        $restoredAt = $startedAt->modify('+181 seconds');

        $status->receiveHeartbeat($restoredAt);

        self::assertSame(PluginStatus::MODE_ACTIVE, $status->getMode());
        self::assertTrue($status->isAutomaticModeAt($restoredAt));
        self::assertSame($restoredAt, $status->getLastHeartbeatAt());
    }
}
