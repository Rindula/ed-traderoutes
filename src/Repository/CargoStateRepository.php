<?php

namespace App\Repository;

use App\Entity\CargoState;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class CargoStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CargoState::class);
    }

    public function findForUser(User $user): ?CargoState
    {
        return $this->findOneBy(['user' => $user]);
    }

    public function findOrCreateForUser(User $user, int $cargoCapacity = 0): CargoState
    {
        $cargoState = $this->findForUser($user);
        if (!$cargoState instanceof CargoState) {
            $cargoState = new CargoState($user, $cargoCapacity);
            $this->getEntityManager()->persist($cargoState);
        }

        return $cargoState;
    }

    public function save(CargoState $cargoState, bool $flush = false): void
    {
        $this->getEntityManager()->persist($cargoState);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
