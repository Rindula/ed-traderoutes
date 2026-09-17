<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Output\OutputInterface;

interface BackupProcessRunnerInterface
{
    /** @param list<string> $command */
    public function run(array $command, OutputInterface $output): int;
}
