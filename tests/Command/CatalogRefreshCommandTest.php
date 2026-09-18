<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CatalogRefreshCommand;
use App\Infrastructure\CatalogImporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CatalogRefreshCommandTest extends TestCase
{
    public function testDryRunDoesNotImportAndAcceptsBounds(): void
    {
        $importer = new FakeCatalogImporter();
        $tester = new CommandTester(new CatalogRefreshCommand($importer));
        self::assertSame(0, $tester->execute(['--dry-run' => true, '--max-systems' => '10', '--max-stations' => '20']));
        self::assertStringContainsString('no data changed', $tester->getDisplay());
        self::assertTrue($importer->options['dryRun']);
        self::assertSame(10, $importer->options['maxSystems']);
    }

    public function testInvalidBoundFailsBeforeImport(): void
    {
        $importer = new FakeCatalogImporter();
        self::assertSame(1, (new CommandTester(new CatalogRefreshCommand($importer)))->execute(['--max-systems' => '0']));
        self::assertNull($importer->options);
    }
}

final class FakeCatalogImporter implements CatalogImporter
{
    public ?array $options = null;
    public function import(array $options): array { $this->options = $options; return ['systems' => 0, 'stations' => 0]; }
}
