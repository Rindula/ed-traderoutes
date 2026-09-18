<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\CatalogImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:catalog:refresh', description: 'Refresh the EDSM system and station catalog.')]
final class CatalogRefreshCommand extends Command
{
    public function __construct(private readonly CatalogImporter $importer) { parent::__construct(); }
    protected function configure(): void
    {
        $this->addOption('systems-url', null, InputOption::VALUE_REQUIRED, 'EDSM systems dump URL.', 'https://www.edsm.net/dump/systemsWithCoordinates.json.gz')
            ->addOption('stations-url', null, InputOption::VALUE_REQUIRED, 'EDSM stations dump URL.', 'https://www.edsm.net/dump/stations.json.gz')
            ->addOption('max-systems', null, InputOption::VALUE_REQUIRED, 'Import at most this many systems.')
            ->addOption('max-stations', null, InputOption::VALUE_REQUIRED, 'Import at most this many stations.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate options without writing or downloading data.');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $maxSystems = $this->positiveInt($input->getOption('max-systems'));
            $maxStations = $this->positiveInt($input->getOption('max-stations'));
            $result = $this->importer->import(['systemsUrl' => (string) $input->getOption('systems-url'), 'stationsUrl' => (string) $input->getOption('stations-url'), 'maxSystems' => $maxSystems, 'maxStations' => $maxStations, 'dryRun' => (bool) $input->getOption('dry-run')]);
            if ($input->getOption('dry-run')) { $output->writeln('<info>CATALOG DRY-RUN: options valid; no data changed.</info>'); }
            else { $output->writeln(sprintf('<info>CATALOG SUCCESS: imported %d system(s) and %d station(s).</info>', $result['systems'], $result['stations'])); }
            return Command::SUCCESS;
        } catch (\Throwable $e) { $output->writeln('<error>CATALOG FAILED: '.$e->getMessage().'</error>'); return Command::FAILURE; }
    }
    private function positiveInt(mixed $value): ?int
    {
        if ($value === null) { return null; }
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1) { throw new \InvalidArgumentException('limits must be positive integers.'); }
        return (int) $value;
    }
}
