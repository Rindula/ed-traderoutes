<?php

namespace App\Repository;

use App\Entity\MarketObservation;
use App\Entity\Station;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class MarketObservationRepository extends ServiceEntityRepository
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
}
