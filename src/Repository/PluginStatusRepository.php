<?php

namespace App\Repository;

use App\Entity\PluginStatus;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class PluginStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PluginStatus::class);
    }

    public function findOrCreateForUser(User $user): PluginStatus
    {
        $status = $this->findOneBy(['user' => $user]);
        if (!$status instanceof PluginStatus) {
            $status = new PluginStatus($user);
            $this->getEntityManager()->persist($status);
        }

        return $status;
    }
}
