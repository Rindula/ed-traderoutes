<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:catalog:refresh', description: 'Refresh the configured system and station catalog.')]
final class CatalogRefreshCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate configuration without importing data.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $provider = getenv('CATALOG_PROVIDER_COMMAND') ?: null;
        if ($input->getOption('dry-run')) {
            $output->writeln($provider === null ? 'CATALOG DRY-RUN: no provider configured.' : 'CATALOG DRY-RUN: provider configured.');
            return Command::SUCCESS;
        }
        if ($provider === null || trim($provider) === '') {
            $output->writeln('<error>CATALOG FAILED: CATALOG_PROVIDER_COMMAND is not configured.</error>');
            return Command::FAILURE;
        }

        $exitCode = 0;
        passthru($provider, $exitCode);
        if ($exitCode !== 0) {
            $output->writeln(sprintf('<error>CATALOG FAILED: provider exited with code %d.</error>', $exitCode));
            return Command::FAILURE;
        }
        $output->writeln('<info>CATALOG SUCCESS: provider completed.</info>');
        return Command::SUCCESS;
    }
}
