<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ShellBackupProcessRunner implements BackupProcessRunnerInterface
{
    public function run(array $command, OutputInterface $output): int
    {
        $pipes = [];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start pg_dump.');
        }

        fclose($pipes[0]);
        stream_copy_to_stream($pipes[1], STDOUT);
        stream_copy_to_stream($pipes[2], STDERR);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process);
    }
}

final class NativeBackupFileStore implements BackupFileStoreInterface
{
    public function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Backup target directory is not writable: %s', $directory));
        }
    }

    public function move(string $source, string $destination): void
    {
        if (!rename($source, $destination)) {
            throw new \RuntimeException(sprintf('Could not move completed backup to %s', $destination));
        }
    }

    public function remove(string $path): void
    {
        if (is_file($path) && !unlink($path)) {
            throw new \RuntimeException(sprintf('Could not remove incomplete backup %s', $path));
        }
    }

    public function isNonEmptyFile(string $path): bool
    {
        return is_file($path) && (filesize($path) ?: 0) > 0;
    }
}

#[AsCommand(
    name: 'backup:database',
    description: 'Create and verify a PostgreSQL backup archive in the mounted backup target.',
)]
final class BackupDatabaseCommand extends Command
{
    public function __construct(
        private readonly ?BackupProcessRunnerInterface $processRunner = null,
        private readonly ?BackupFileStoreInterface $fileStore = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('target-dir', null, InputOption::VALUE_REQUIRED, 'Mounted backup directory (or BACKUP_TARGET_DIR).')
            ->addOption('database-url', null, InputOption::VALUE_REQUIRED, 'PostgreSQL DSN (or DATABASE_URL).')
            ->addOption('pg-dump', null, InputOption::VALUE_REQUIRED, 'pg_dump executable (or PG_DUMP).', 'pg_dump')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Log the planned backup without touching the database or filesystem.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetDirectory = $input->getOption('target-dir') ?: getenv('BACKUP_TARGET_DIR');
        $databaseUrl = $input->getOption('database-url') ?: getenv('DATABASE_URL');
        $pgDump = $input->getOption('pg-dump') ?: (getenv('PG_DUMP') ?: 'pg_dump');

        if (!is_string($targetDirectory) || $targetDirectory === '') {
            $output->writeln('<error>BACKUP FAILED: no backup target directory configured.</error>');
            return Command::FAILURE;
        }

        if (!is_string($databaseUrl) || $databaseUrl === '') {
            $output->writeln('<error>BACKUP FAILED: no PostgreSQL database URL configured.</error>');
            return Command::FAILURE;
        }

        $fileName = sprintf('postgresql-%s-%s.dump', gmdate('Ymd\THis\Z'), bin2hex(random_bytes(4)));
        $destination = rtrim($targetDirectory, '/').'/'.$fileName;
        $temporary = $destination.'.part';
        $command = [$pgDump, '--format=custom', '--file='.$temporary, '--dbname='.$databaseUrl];

        if ($input->getOption('dry-run')) {
            $output->writeln(sprintf('BACKUP DRY-RUN: would write PostgreSQL archive to %s', $destination));
            return Command::SUCCESS;
        }

        $store = $this->fileStore ?? new NativeBackupFileStore();
        $runner = $this->processRunner ?? new ShellBackupProcessRunner();

        try {
            $store->ensureDirectory($targetDirectory);
            $output->writeln(sprintf('BACKUP START: PostgreSQL archive target=%s', $destination));

            $exitCode = $runner->run($command, $output);
            if ($exitCode !== 0) {
                $store->remove($temporary);
                $output->writeln(sprintf('<error>BACKUP FAILED: pg_dump exited with code %d; retry the job.</error>', $exitCode));
                return Command::FAILURE;
            }

            if (!$store->isNonEmptyFile($temporary)) {
                $store->remove($temporary);
                $output->writeln('<error>BACKUP FAILED: pg_dump completed but produced no non-empty archive; retry the job.</error>');
                return Command::FAILURE;
            }

            $store->move($temporary, $destination);
            if (!$store->isNonEmptyFile($destination)) {
                $output->writeln('<error>BACKUP FAILED: completed archive is missing or empty on the target; retry the job.</error>');
                return Command::FAILURE;
            }
        } catch (\Throwable $exception) {
            try {
                $store->remove($temporary);
            } catch (\Throwable) {
                // Preserve the original operational error in the alert output.
            }

            $output->writeln(sprintf('<error>BACKUP FAILED: %s; retry the job.</error>', $exception->getMessage()));
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>BACKUP SUCCESS: verified non-empty PostgreSQL archive %s</info>', $destination));
        return Command::SUCCESS;
    }
}
