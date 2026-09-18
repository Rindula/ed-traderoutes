<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Component\Uid\Ulid;
use RuntimeException;

final class EdsmCatalogImporter implements CatalogImporter
{
    private const SOURCE = 'edsm';
    public function __construct(private readonly Connection $connection, private readonly EdsmJsonObjectStream $parser, private readonly EdsmCatalogMapper $mapper, private readonly HttpClientInterface $httpClient) {}

    public function import(array $options): array
    {
        foreach (['systemsUrl', 'stationsUrl'] as $key) {
            if (!filter_var($options[$key] ?? null, FILTER_VALIDATE_URL)) { throw new RuntimeException('Invalid '.$key.'.'); }
        }
        if ($options['dryRun']) { return ['systems' => 0, 'stations' => 0]; }
        $now = new \DateTimeImmutable();
        $systems = $this->importSystems($options['systemsUrl'], $options['maxSystems'], $now);
        $stations = $this->importStations($options['stationsUrl'], $options['maxStations'], $now);
        return ['systems' => $systems, 'stations' => $stations];
    }

    private function importSystems(string $url, ?int $max, \DateTimeImmutable $now): int
    {
        $count = 0; $batch = 0;
        $this->connection->beginTransaction();
        try {
            foreach ($this->objects($url) as $record) {
                if ($max !== null && $count >= $max) { break; }
                $data = $this->mapper->system($record);
                $this->connection->executeStatement('INSERT INTO catalog_system (id,name,x,y,z,accessible,catalog_source,catalog_observed_at,updated_at) VALUES (:id,:name,:x,:y,:z,:accessible,:source,:observed,:updated) ON CONFLICT (name) DO UPDATE SET x=EXCLUDED.x,y=EXCLUDED.y,z=EXCLUDED.z,accessible=EXCLUDED.accessible,catalog_source=EXCLUDED.catalog_source,catalog_observed_at=EXCLUDED.catalog_observed_at,updated_at=EXCLUDED.updated_at', $this->systemParams($data, $now));
                ++$count; if (++$batch >= 500) { $this->connection->commit(); $this->connection->beginTransaction(); $batch = 0; }
            }
            $this->connection->commit();
        } catch (\Throwable $e) { if ($this->connection->isTransactionActive()) { $this->connection->rollBack(); } throw $e; }
        return $count;
    }

    private function importStations(string $url, ?int $max, \DateTimeImmutable $now): int
    {
        $count = 0; $batch = 0;
        $this->connection->beginTransaction();
        try {
            foreach ($this->objects($url) as $record) {
                if ($max !== null && $count >= $max) { break; }
                $data = $this->mapper->station($record);
                $systemId = $this->connection->fetchOne('SELECT id FROM catalog_system WHERE name = ?', [$data['systemName']]);
                if ($systemId === false) { continue; }
                $params = ['id' => (string) new Ulid(), 'systemId' => $systemId, 'name' => $data['name'], 'marketId' => $data['marketId'], 'stationType' => $data['stationType'], 'landingClass' => $data['landingClass'], 'hasMarket' => $data['hasMarket'], 'accessible' => $data['accessible'], 'source' => self::SOURCE, 'observed' => $now->format('Y-m-d H:i:s'), 'updated' => $now->format('Y-m-d H:i:s')];
                $this->connection->executeStatement('INSERT INTO catalog_station (id,system_id,name,market_id,station_type,landing_class,has_market,accessible,catalog_source,catalog_observed_at,updated_at) VALUES (:id,:systemId,:name,:marketId,:stationType,:landingClass,:hasMarket,:accessible,:source,:observed,:updated) ON CONFLICT (system_id,name) DO UPDATE SET market_id=EXCLUDED.market_id,station_type=EXCLUDED.station_type,landing_class=EXCLUDED.landing_class,has_market=EXCLUDED.has_market,accessible=EXCLUDED.accessible,catalog_source=EXCLUDED.catalog_source,catalog_observed_at=EXCLUDED.catalog_observed_at,updated_at=EXCLUDED.updated_at', $params);
                ++$count; if (++$batch >= 500) { $this->connection->commit(); $this->connection->beginTransaction(); $batch = 0; }
            }
            $this->connection->commit();
        } catch (\Throwable $e) { if ($this->connection->isTransactionActive()) { $this->connection->rollBack(); } throw $e; }
        return $count;
    }

    private function systemParams(array $data, \DateTimeImmutable $now): array { return ['id' => (string) new Ulid(), ...$data, 'source' => self::SOURCE, 'observed' => $now->format('Y-m-d H:i:s'), 'updated' => $now->format('Y-m-d H:i:s')]; }

    /** @return \Generator<int, array<string,mixed>> */
    private function objects(string $url): \Generator
    {
        $response = $this->httpClient->request('GET', $url, ['buffer' => false, 'headers' => ['Accept-Encoding' => 'gzip']]);
        try { $status = $response->getStatusCode(); } catch (\Throwable $e) { throw new RuntimeException('EDSM HTTP request failed: '.$e->getMessage(), 0, $e); }
        if ($status < 200 || $status >= 300) { throw new RuntimeException('EDSM HTTP request returned status '.$status.'.'); }
        $chunks = (function () use ($response): \Generator { foreach ($this->httpClient->stream($response) as $chunk) { if (!$chunk->isTimeout()) { yield $chunk->getContent(); } } })();
        yield from $this->parser->decode($chunks);
    }
}
