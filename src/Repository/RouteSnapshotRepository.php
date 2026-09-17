<?php

namespace App\Repository;

use App\Entity\RouteSnapshot;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class RouteSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RouteSnapshot::class);
    }

    public function findLatestForUser(User $user): ?RouteSnapshot
    {
        return $this->createQueryBuilder('snapshot')
            ->andWhere('snapshot.user = :user')
            ->setParameter('user', $user)
            ->orderBy('snapshot.calculatedAt', 'DESC')
            ->addOrderBy('snapshot.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(RouteSnapshot $snapshot, bool $flush = false): void
    {
        $this->getEntityManager()->persist($snapshot);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
