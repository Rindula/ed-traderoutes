<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\BackupDatabaseCommand;
use App\Command\BackupFileStoreInterface;
use App\Command\BackupProcessRunnerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Output\OutputInterface;

final class BackupDatabaseCommandTest extends TestCase
{
    public function testSuccessfulBackupIsMovedAndVerified(): void
    {
        $store = new InMemoryBackupFileStore();
        $runner = new RecordingBackupRunner($store, 0);
        $tester = new CommandTester(new BackupDatabaseCommand($runner, $store));

        self::assertSame(Command::SUCCESS, $tester->execute([
            '--target-dir' => '/backup',
            '--database-url' => 'postgresql://user:secret@postgres/app',
        ]));
        self::assertStringContainsString('BACKUP SUCCESS: verified non-empty PostgreSQL archive', $tester->getDisplay());
        self::assertCount(1, $store->moved);
        self::assertStringNotContainsString('secret', $tester->getDisplay());
    }

    public function testPgDumpFailureReturnsNonZeroAndLeavesNoPartialFile(): void
    {
        $store = new InMemoryBackupFileStore();
        $runner = new RecordingBackupRunner($store, 2);
        $tester = new CommandTester(new BackupDatabaseCommand($runner, $store));

        self::assertSame(Command::FAILURE, $tester->execute([
            '--target-dir' => '/smb-backup',
            '--database-url' => 'postgresql://postgres/app',
        ]));
        self::assertStringContainsString('pg_dump exited with code 2', $tester->getDisplay());
        self::assertSame([], $store->files);
    }

    public function testDryRunDoesNotInvokeRunnerOrWriteToTarget(): void
    {
        $store = new InMemoryBackupFileStore();
        $runner = new RecordingBackupRunner($store, 0);
        $tester = new CommandTester(new BackupDatabaseCommand($runner, $store));

        self::assertSame(Command::SUCCESS, $tester->execute([
            '--target-dir' => '/smb-backup',
            '--database-url' => 'postgresql://postgres/app',
            '--dry-run' => true,
        ]));
        self::assertStringContainsString('BACKUP DRY-RUN:', $tester->getDisplay());
        self::assertNull($runner->command);
        self::assertSame([], $store->files);
    }

    public function testTargetWriteFailureReturnsNonZero(): void
    {
        $store = new InMemoryBackupFileStore();
        $store->throwOnMove = true;
        $runner = new RecordingBackupRunner($store, 0);
        $tester = new CommandTester(new BackupDatabaseCommand($runner, $store));

        self::assertSame(Command::FAILURE, $tester->execute([
            '--target-dir' => '/smb-backup',
            '--database-url' => 'postgresql://postgres/app',
        ]));
        self::assertStringContainsString('BACKUP FAILED:', $tester->getDisplay());
        self::assertStringContainsString('retry the job', $tester->getDisplay());
    }

    public function testEmptyArchiveReturnsNonZero(): void
    {
        $store = new InMemoryBackupFileStore();
        $runner = new RecordingBackupRunner($store, 0, '');
        $tester = new CommandTester(new BackupDatabaseCommand($runner, $store));

        self::assertSame(Command::FAILURE, $tester->execute([
            '--target-dir' => '/smb-backup',
            '--database-url' => 'postgresql://postgres/app',
        ]));
        self::assertStringContainsString('produced no non-empty archive', $tester->getDisplay());
        self::assertSame([], $store->files);
    }
}

final class RecordingBackupRunner implements BackupProcessRunnerInterface
{
    public ?array $command = null;

    public function __construct(
        private readonly InMemoryBackupFileStore $store,
        private readonly int $exitCode,
        private readonly string $archiveContents = 'archive',
    ) {}

    public function run(array $command, OutputInterface $output): int
    {
        $this->command = $command;
        if ($this->exitCode === 0) {
            $this->store->files[$this->store->temporaryPath($command)] = $this->archiveContents;
        }

        return $this->exitCode;
    }
}

final class InMemoryBackupFileStore implements BackupFileStoreInterface
{
    /** @var array<string, string> */
    public array $files = [];
    /** @var list<string> */
    public array $moved = [];
    public bool $throwOnMove = false;

    public function ensureDirectory(string $directory): void {}

    public function move(string $source, string $destination): void
    {
        if ($this->throwOnMove) {
            throw new \RuntimeException('SMB target is unavailable');
        }

        $this->files[$destination] = $this->files[$source];
        unset($this->files[$source]);
        $this->moved[] = $destination;
    }

    public function remove(string $path): void
    {
        unset($this->files[$path]);
    }

    public function isNonEmptyFile(string $path): bool
    {
        return isset($this->files[$path]) && $this->files[$path] !== '';
    }

    /** @param list<string> $command */
    public function temporaryPath(array $command): string
    {
        foreach ($command as $argument) {
            if (str_starts_with($argument, '--file=')) {
                return substr($argument, 7);
            }
        }

        throw new \LogicException('The backup command has no output file.');
    }
}
