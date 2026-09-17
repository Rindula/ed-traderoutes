<?php

namespace App\Tests\Plugin;

use App\Entity\SyncKey;
use App\Entity\User;
use App\Security\SyncKeyCipher;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SyncKeySecurityTest extends TestCase
{
    public function testSynchronizationKeyIsMaskedByDefaultAndCanBeRevealedWithTheCipher(): void
    {
        $cipher = new SyncKeyCipher('test-app-secret');
        $key = SyncKey::create(new User('authentik-subject'), $cipher, new DateTimeImmutable('2026-09-17T12:00:00+00:00'));

        $masked = $key->getMaskedToken($cipher);
        $revealed = $key->revealToken($cipher);

        self::assertSame(strlen($revealed), strlen($masked));
        self::assertSame(substr($revealed, 0, 4), substr($masked, 0, 4));
        self::assertSame(substr($revealed, -4), substr($masked, -4));
        self::assertSame(str_repeat('*', strlen($revealed) - 8), substr($masked, 4, -4));
        self::assertNotSame($revealed, $masked);
    }

    public function testRevokedSynchronizationKeyIsMarkedRevokedAndRemainsIndividuallyIdentifiable(): void
    {
        $cipher = new SyncKeyCipher('test-app-secret');
        $key = SyncKey::create(new User('authentik-subject'), $cipher);
        $revokedAt = new DateTimeImmutable('2026-09-17T12:01:00+00:00');

        $key->revoke($revokedAt);

        self::assertTrue($key->isRevoked());
        self::assertSame($revokedAt, $key->getRevokedAt());
        self::assertNotSame('', $key->getKeyIdentifier());
        self::assertSame($key->getKeyIdentifier(), $key->getIdentifier());
    }
}
