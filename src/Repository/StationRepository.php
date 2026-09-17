<?php

namespace App\Repository;

use App\Entity\Station;
use App\Entity\System;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class StationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Station::class);
    }

    public function findBySystemAndName(System $system, string $name): ?Station
    {
        return $this->findOneBy(['system' => $system, 'name' => $name]);
    }

    public function findByMarketId(string $marketId): ?Station
    {
        return $this->findOneBy(['marketId' => $marketId]);
    }

    /**
     * @param list<string> $stationTypes
     * @return list<Station>
     */
    public function findAccessibleMarkets(?string $landingClass = null, array $stationTypes = []): array
    {
        $query = $this->createQueryBuilder('station')
            ->innerJoin('station.system', 'system')
            ->andWhere('station.hasMarket = :hasMarket')
            ->andWhere('station.accessible = :stationAccessible')
            ->andWhere('system.accessible = :systemAccessible')
            ->setParameter('hasMarket', true)
            ->setParameter('stationAccessible', true)
            ->setParameter('systemAccessible', true)
            ->orderBy('system.name', 'ASC')
            ->addOrderBy('station.name', 'ASC');

        if ($landingClass !== null) {
            $query->andWhere('station.landingClass = :landingClass')
                ->setParameter('landingClass', $landingClass);
        }

        if ($stationTypes !== []) {
            $query->andWhere('station.stationType IN (:stationTypes)')
                ->setParameter('stationTypes', $stationTypes);
        }

        return $query->getQuery()->getResult();
    }

    public function save(Station $station, bool $flush = false): void
    {
        $this->getEntityManager()->persist($station);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
