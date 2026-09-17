<?php

declare(strict_types=1);

namespace App\Command;

interface BackupFileStoreInterface
{
    public function ensureDirectory(string $directory): void;
    public function move(string $source, string $destination): void;
    public function remove(string $path): void;
    public function isNonEmptyFile(string $path): bool;
}
