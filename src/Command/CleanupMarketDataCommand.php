<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\MarketObservationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

interface CleanupMarketDataStore
{
    public function cleanupNormalizedOlderThan(\DateTimeImmutable $cutoff, bool $dryRun): int;

    /** A null result means that no supported raw EDDN table/entity exists. */
    public function cleanupRawEddnOlderThan(\DateTimeImmutable $cutoff, bool $dryRun): ?int;
}

#[AsCommand(
    name: 'app:cleanup-market-data',
    description: 'Remove expired market history and raw EDDN messages.',
)]
final class CleanupMarketDataCommand extends Command
{
    public function __construct(
        #[Autowire(service: MarketObservationRepository::class)]
        private readonly CleanupMarketDataStore $store,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('normalized-retention-days', null, InputOption::VALUE_REQUIRED, 'Normalized history retention in days.', '30')
            ->addOption('raw-retention-hours', null, InputOption::VALUE_REQUIRED, 'Raw EDDN retention in hours.', '72')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be removed without deleting it.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $normalizedDays = $this->positiveInteger($input->getOption('normalized-retention-days'), 'normalized-retention-days');
            $rawHours = $this->positiveInteger($input->getOption('raw-retention-hours'), 'raw-retention-hours');
        } catch (\InvalidArgumentException $exception) {
            $output->writeln('<error>'.$exception->getMessage().'</error>');

            return Command::INVALID;
        }

        $now = new \DateTimeImmutable();
        $dryRun = (bool) $input->getOption('dry-run');
        $normalizedCount = $this->store->cleanupNormalizedOlderThan($now->modify(sprintf('-%d days', $normalizedDays)), $dryRun);
        $rawCount = $this->store->cleanupRawEddnOlderThan($now->modify(sprintf('-%d hours', $rawHours)), $dryRun);

        $prefix = $dryRun ? 'Would remove' : 'Removed';
        $output->writeln(sprintf('%s %d normalized market observation(s).', $prefix, $normalizedCount));
        if ($rawCount === null) {
            $output->writeln('Raw EDDN cleanup skipped: no supported raw EDDN table/entity is configured.');
        } else {
            $output->writeln(sprintf('%s %d raw EDDN message(s).', $prefix, $rawCount));
        }

        return Command::SUCCESS;
    }

    private function positiveInteger(mixed $value, string $option): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($parsed === false) {
            throw new \InvalidArgumentException(sprintf('--%s must be a positive integer.', $option));
        }

        return $parsed;
    }
}
