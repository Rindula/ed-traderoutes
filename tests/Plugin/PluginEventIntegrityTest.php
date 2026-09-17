<?php

namespace App\Tests\Plugin;

use App\Entity\SyncEvent;
use App\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PluginEventIntegrityTest extends TestCase
{
    public function testRetryingAnEventWithTheSameUserAndExternalIdentityIsIdempotent(): void
    {
        $user = new User('authentik-subject');
        $sourceTimestamp = new DateTimeImmutable('2026-09-17T12:00:00+00:00');
        $event = $this->event($user, 'event-001', 1, $sourceTimestamp);
        $retry = $this->event($user, 'event-001', 1, $sourceTimestamp);

        self::assertTrue($event->hasSameIdempotencyIdentityAs($retry));
        self::assertSame($event->getIdempotencyKey(), $retry->getIdempotencyKey());
        self::assertTrue($event->isEquivalentTo($retry));
        self::assertFalse($event->isConflictingDuplicateOf($retry));
    }

    public function testSameExternalIdentityWithDifferentPayloadIsAConflictingDuplicate(): void
    {
        $user = new User('authentik-subject');
        $sourceTimestamp = new DateTimeImmutable('2026-09-17T12:00:00+00:00');
        $event = $this->event($user, 'event-001', 1, $sourceTimestamp, ['system' => 'Sol']);
        $conflict = $this->event($user, 'event-001', 1, $sourceTimestamp, ['system' => 'Achenar']);

        self::assertTrue($event->isConflictingDuplicateOf($conflict));
        self::assertFalse($event->isEquivalentTo($conflict));
    }

    public function testASequenceGapMarksThePluginCargoStateAsUncertain(): void
    {
        $user = new User('authentik-subject');
        $first = $this->event($user, 'event-001', 1, new DateTimeImmutable('2026-09-17T12:00:00+00:00'));
        $third = $this->event($user, 'event-003', 3, new DateTimeImmutable('2026-09-17T12:02:00+00:00'));

        self::assertTrue($third->hasUncertainSequenceAfter($first));
        self::assertFalse($third->isContiguousAfter($first));
    }

    public function testRetryDoesNotCreateASequenceGap(): void
    {
        $user = new User('authentik-subject');
        $event = $this->event($user, 'event-001', 1, new DateTimeImmutable('2026-09-17T12:00:00+00:00'));
        $retry = $this->event($user, 'event-001', 1, new DateTimeImmutable('2026-09-17T12:00:00+00:00'));

        self::assertFalse($retry->hasUncertainSequenceAfter($event));
        self::assertTrue($retry->isContiguousAfter($event));
    }

    private function event(
        User $user,
        string $externalId,
        int $sequence,
        DateTimeImmutable $sourceTimestamp,
        array $payload = [],
    ): SyncEvent {
        return new SyncEvent($user, $externalId, $sequence, 'Docked', $sourceTimestamp, $payload, $sourceTimestamp);
    }
}
