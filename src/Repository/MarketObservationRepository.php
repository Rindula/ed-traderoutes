<?php

namespace App\Repository;

use App\Command\CleanupMarketDataStore;
use App\Entity\MarketObservation;
use App\Entity\Station;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

final class MarketObservationRepository extends ServiceEntityRepository implements CleanupMarketDataStore
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MarketObservation::class);
    }

    public function findCurrentForStationAndCommodity(Station $station, string $commodityName): ?MarketObservation
    {
        return $this->createQueryBuilder('observation')
            ->andWhere('observation.station = :station')
            ->andWhere('observation.commodityName = :commodityName')
            ->setParameter('station', $station)
            ->setParameter('commodityName', $commodityName)
            ->orderBy('observation.observedAt', 'DESC')
            ->addOrderBy('observation.receivedAt', 'DESC')
            ->addOrderBy('observation.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findFreshCurrentForStationAndCommodity(
        Station $station,
        string $commodityName,
        \DateTimeImmutable $asOf,
        int $maxAgeSeconds = 7200,
    ): ?MarketObservation {
        if ($maxAgeSeconds < 0) {
            return null;
        }

        return $this->createQueryBuilder('observation')
            ->andWhere('observation.station = :station')
            ->andWhere('observation.commodityName = :commodityName')
            ->andWhere('observation.observedAt BETWEEN :oldest AND :asOf')
            ->setParameter('station', $station)
            ->setParameter('commodityName', $commodityName)
            ->setParameter('oldest', $asOf->modify(sprintf('-%d seconds', $maxAgeSeconds)))
            ->setParameter('asOf', $asOf)
            ->orderBy('observation.observedAt', 'DESC')
            ->addOrderBy('observation.receivedAt', 'DESC')
            ->addOrderBy('observation.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<MarketObservation> */
    public function findCurrentForStation(Station $station): array
    {
        $observations = $this->createQueryBuilder('observation')
            ->andWhere('observation.station = :station')
            ->setParameter('station', $station)
            ->orderBy('observation.commodityName', 'ASC')
            ->addOrderBy('observation.observedAt', 'DESC')
            ->addOrderBy('observation.receivedAt', 'DESC')
            ->addOrderBy('observation.id', 'DESC')
            ->getQuery()
            ->getResult();

        $current = [];
        foreach ($observations as $observation) {
            $commodityName = $observation->getCommodityName();
            if (!isset($current[$commodityName])) {
                $current[$commodityName] = $observation;
            }
        }

        return array_values($current);
    }

    /** @return list<MarketObservation> */
    public function findFreshForStation(
        Station $station,
        \DateTimeImmutable $asOf,
        int $maxAgeSeconds = 7200,
    ): array {
        if ($maxAgeSeconds < 0) {
            return [];
        }

        return $this->createQueryBuilder('observation')
            ->andWhere('observation.station = :station')
            ->andWhere('observation.observedAt BETWEEN :oldest AND :asOf')
            ->setParameter('station', $station)
            ->setParameter('oldest', $asOf->modify(sprintf('-%d seconds', $maxAgeSeconds)))
            ->setParameter('asOf', $asOf)
            ->orderBy('observation.commodityName', 'ASC')
            ->addOrderBy('observation.observedAt', 'DESC')
            ->addOrderBy('observation.receivedAt', 'DESC')
            ->addOrderBy('observation.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(MarketObservation $observation, bool $flush = false): void
    {
        $this->getEntityManager()->persist($observation);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function cleanupNormalizedOlderThan(\DateTimeImmutable $cutoff, bool $dryRun): int
    {
        $observations = $this->createQueryBuilder('observation')
            ->orderBy('IDENTITY(observation.station)', 'ASC')
            ->addOrderBy('observation.commodityName', 'ASC')
            ->addOrderBy('observation.observedAt', 'DESC')
            ->addOrderBy('observation.receivedAt', 'DESC')
            ->addOrderBy('observation.id', 'DESC')
            ->getQuery()
            ->getResult();

        $currentKeys = [];
        $removed = 0;
        foreach ($observations as $observation) {
            $key = $observation->getStation()->getId()."\0".$observation->getCommodityName();
            if (isset($currentKeys[$key])) {
                if ($observation->getObservedAt() < $cutoff) {
                    ++$removed;
                    if (!$dryRun) {
                        $this->getEntityManager()->remove($observation);
                    }
                }
            } else {
                $currentKeys[$key] = true;
            }
        }

        if (!$dryRun && $removed > 0) {
            $this->getEntityManager()->flush();
        }

        return $removed;
    }

    public function cleanupRawEddnOlderThan(\DateTimeImmutable $cutoff, bool $dryRun): ?int
    {
        $connection = $this->getEntityManager()->getConnection();
        $schemaManager = $connection->createSchemaManager();
        $table = null;
        foreach (['raw_eddn_message', 'eddn_message'] as $candidate) {
            if ($schemaManager->tablesExist([$candidate])) {
                $table = $candidate;
                break;
            }
        }

        if ($table === null) {
            return null;
        }

        $columns = array_map('strtolower', array_keys($schemaManager->listTableColumns($table)));
        $timestampColumn = in_array('received_at', $columns, true) ? 'received_at' : (in_array('created_at', $columns, true) ? 'created_at' : null);
        if ($timestampColumn === null) {
            return null;
        }

        $quotedTable = $connection->quoteIdentifier($table);
        $quotedColumn = $connection->quoteIdentifier($timestampColumn);
        $parameters = ['cutoff' => $cutoff];
        $types = ['cutoff' => Types::DATETIME_IMMUTABLE];
        $count = (int) $connection->fetchOne("SELECT COUNT(*) FROM {$quotedTable} WHERE {$quotedColumn} < :cutoff", $parameters, $types);
        if (!$dryRun && $count > 0) {
            $connection->executeStatement("DELETE FROM {$quotedTable} WHERE {$quotedColumn} < :cutoff", $parameters, $types);
        }

        return $count;
    }
}
