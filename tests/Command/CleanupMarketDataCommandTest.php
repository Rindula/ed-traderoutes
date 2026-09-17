<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CleanupMarketDataCommand;
use App\Command\CleanupMarketDataStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

// The interface intentionally lives beside the command to keep this feature's seam local.
class_exists(CleanupMarketDataCommand::class);

final class CleanupMarketDataCommandTest extends TestCase
{
    public function testItReportsCountsAndRawNoOp(): void
    {
        $store = new InMemoryCleanupStore(12, null);
        $tester = new CommandTester(new CleanupMarketDataCommand($store));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Removed 12 normalized market observation(s).', $tester->getDisplay());
        self::assertStringContainsString('Raw EDDN cleanup skipped', $tester->getDisplay());
        self::assertFalse($store->dryRun);
    }

    public function testDryRunReportsWithoutMutating(): void
    {
        $store = new InMemoryCleanupStore(4, 7);
        $tester = new CommandTester(new CleanupMarketDataCommand($store));

        self::assertSame(0, $tester->execute(['--dry-run' => true, '--normalized-retention-days' => 10, '--raw-retention-hours' => 24]));
        self::assertStringContainsString('Would remove 4 normalized market observation(s).', $tester->getDisplay());
        self::assertStringContainsString('Would remove 7 raw EDDN message(s).', $tester->getDisplay());
        self::assertTrue($store->dryRun);
    }

    public function testRetentionOptionsMustBePositiveIntegers(): void
    {
        $tester = new CommandTester(new CleanupMarketDataCommand(new InMemoryCleanupStore(0, null)));

        self::assertSame(2, $tester->execute(['--raw-retention-hours' => '0']));
        self::assertStringContainsString('must be a positive integer', $tester->getDisplay());
    }
}

final class InMemoryCleanupStore implements CleanupMarketDataStore
{
    public bool $dryRun = false;

    public function __construct(private readonly int $normalized, private readonly ?int $raw) {}

    public function cleanupNormalizedOlderThan(\DateTimeImmutable $cutoff, bool $dryRun): int
    {
        $this->dryRun = $dryRun;
        return $this->normalized;
    }

    public function cleanupRawEddnOlderThan(\DateTimeImmutable $cutoff, bool $dryRun): ?int
    {
        $this->dryRun = $dryRun;
        return $this->raw;
    }
}
