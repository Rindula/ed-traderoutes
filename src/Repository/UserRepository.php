<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, User::class); }

    public function findOrCreateFromOidc(string $subject, ?string $email, ?string $displayName): User
    {
        $user = $this->findOneBy(['subject' => $subject]);
        if (!$user instanceof User) {
            $user = new User($subject, $email, $displayName);
            $this->getEntityManager()->persist($user);
        } else {
            $user->updateProfile($email, $displayName);
        }
        $this->getEntityManager()->flush();

        return $user;
    }
}
