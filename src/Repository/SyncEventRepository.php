<?php

namespace App\Repository;

use App\Entity\SyncEvent;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class SyncEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SyncEvent::class);
    }

    public function findByIdempotencyIdentity(User $user, string $externalEventId): ?SyncEvent
    {
        return $this->findOneBy([
            'user' => $user,
            'externalEventId' => $externalEventId,
        ]);
    }

    public function findPreviousForSequence(User $user, int $sequence): ?SyncEvent
    {
        return $this->createQueryBuilder('event')
            ->andWhere('event.user = :user')
            ->andWhere('event.sequence < :sequence')
            ->setParameter('user', $user)
            ->setParameter('sequence', $sequence)
            ->orderBy('event.sequence', 'DESC')
            ->addOrderBy('event.sourceTimestamp', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestForUser(User $user): ?SyncEvent
    {
        return $this->createQueryBuilder('event')
            ->andWhere('event.user = :user')
            ->setParameter('user', $user)
            ->orderBy('event.sequence', 'DESC')
            ->addOrderBy('event.sourceTimestamp', 'DESC')
            ->addOrderBy('event.receivedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasDifferentEventAtSequence(User $user, int $sequence, string $externalEventId): bool
    {
        return (bool) $this->createQueryBuilder('event')
            ->select('1')
            ->andWhere('event.user = :user')
            ->andWhere('event.sequence = :sequence')
            ->andWhere('event.externalEventId <> :externalEventId')
            ->setParameter('user', $user)
            ->setParameter('sequence', $sequence)
            ->setParameter('externalEventId', $externalEventId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
