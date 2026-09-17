<?php

namespace App\Repository;

use App\Entity\System;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class SystemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, System::class);
    }

    public function findByName(string $name): ?System
    {
        return $this->findOneBy(['name' => $name]);
    }

    /** @return list<System> */
    public function findAccessible(?int $limit = null): array
    {
        $query = $this->createQueryBuilder('system')
            ->andWhere('system.accessible = :accessible')
            ->setParameter('accessible', true)
            ->orderBy('system.name', 'ASC')
            ->getQuery();

        if ($limit !== null) {
            $query->setMaxResults($limit);
        }

        return $query->getResult();
    }

    public function save(System $system, bool $flush = false): void
    {
        $this->getEntityManager()->persist($system);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
