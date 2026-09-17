<?php

namespace App\Repository;

use App\Entity\ActiveRoute;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class ActiveRouteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActiveRoute::class);
    }

    public function findForUser(User $user): ?ActiveRoute
    {
        return $this->findOneBy(['user' => $user]);
    }

    public function save(ActiveRoute $activeRoute, bool $flush = false): void
    {
        $this->getEntityManager()->persist($activeRoute);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
