<?php

declare(strict_types=1);

namespace App\Tests\Deploy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class KubernetesManifestTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function documents(): array
    {
        $path = dirname(__DIR__, 2).'/deploy/kubernetes/ticket-9.yaml';
        $documents = preg_split('/^---\s*$/m', (string) file_get_contents($path), -1, PREG_SPLIT_NO_EMPTY);

        return array_map(static fn (string $document): array => Yaml::parse($document), $documents);
    }

    public function testManifestHasExplicitApplicationStorageAndDedicatedSmbBackup(): void
    {
        $documents = $this->documents();
        $statefulSets = array_values(array_filter($documents, static fn (array $doc): bool => $doc['kind'] === 'StatefulSet'));

        self::assertCount(2, $statefulSets);
        foreach ($statefulSets as $statefulSet) {
            self::assertNotEmpty($statefulSet['spec']['volumeClaimTemplates'][0]['spec']['storageClassName']);
        }

        $backup = $this->find('CronJob', 'ed-traderoutes-postgres-backup', $documents);
        $backupYaml = Yaml::dump($backup);
        self::assertStringContainsString('smb-backup', $backupYaml);

        foreach ($documents as $document) {
            if (in_array($document['kind'], ['Deployment', 'StatefulSet'], true)) {
                self::assertStringNotContainsString('smb-backup', Yaml::dump($document));
            }
        }
    }

    public function testScheduledOperationsAreRetryableAndMonitored(): void
    {
        $documents = $this->documents();
        foreach (['ed-traderoutes-catalog-weekly', 'ed-traderoutes-retention-cleanup', 'ed-traderoutes-postgres-backup'] as $name) {
            $cronJob = $this->find('CronJob', $name, $documents);
            self::assertSame('required', $cronJob['metadata']['labels']['monitoring']);
            self::assertSame('job-failed', $cronJob['metadata']['annotations']['monitoring.ed-traderoutes.io/failure-signal']);
            self::assertSame('Forbid', $cronJob['spec']['concurrencyPolicy']);
            self::assertGreaterThan(0, $cronJob['spec']['jobTemplate']['spec']['backoffLimit']);
        }

        $manual = $this->find('Job', 'ed-traderoutes-catalog-manual', $documents);
        self::assertTrue($manual['spec']['suspend']);
    }

    /** @param list<array<string, mixed>> $documents */
    private function find(string $kind, string $name, array $documents): array
    {
        foreach ($documents as $document) {
            if ($document['kind'] === $kind && $document['metadata']['name'] === $name) {
                return $document;
            }
        }

        self::fail(sprintf('%s %s not found', $kind, $name));
    }
}
