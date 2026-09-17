<?php

namespace App\Repository;

use App\Entity\SyncKey;
use App\Entity\User;
use App\Security\SyncKeyCipher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class SyncKeyRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly SyncKeyCipher $cipher,
    ) {
        parent::__construct($registry, SyncKey::class);
    }

    public function createForUser(User $user, ?\DateTimeImmutable $createdAt = null): SyncKey
    {
        $syncKey = SyncKey::create($user, $this->cipher, $createdAt);
        $this->getEntityManager()->persist($syncKey);

        return $syncKey;
    }

    public function save(SyncKey $syncKey, bool $flush = false): void
    {
        $this->getEntityManager()->persist($syncKey);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** @return list<SyncKey> */
    public function findForUser(User $user, bool $includeRevoked = false): array
    {
        $query = $this->createQueryBuilder('syncKey')
            ->andWhere('syncKey.user = :user')
            ->setParameter('user', $user)
            ->orderBy('syncKey.createdAt', 'DESC');

        if (!$includeRevoked) {
            $query->andWhere('syncKey.revokedAt IS NULL');
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Authenticate against every active key and never stop before all candidates
     * have been compared. This keeps the comparison work independent of the
     * matching key's position in the result set.
     */
    public function findByPresentedToken(string $presentedToken): ?SyncKey
    {
        if ($presentedToken === '') {
            return null;
        }

        /** @var iterable<SyncKey> $candidates */
        $candidates = $this->createQueryBuilder('syncKey')
            ->andWhere('syncKey.revokedAt IS NULL')
            ->getQuery()
            ->toIterable();

        $match = null;
        foreach ($candidates as $candidate) {
            if ($candidate->matchesPresentedToken($presentedToken, $this->cipher)) {
                $match = $candidate;
            }
        }

        return $match;
    }

    public function findByIdentifierAndPresentedToken(
        string $keyIdentifier,
        string $presentedToken,
    ): ?SyncKey {
        if ($keyIdentifier === '' || $presentedToken === '') {
            return null;
        }

        $candidate = $this->createQueryBuilder('syncKey')
            ->andWhere('syncKey.id = :keyIdentifier')
            ->andWhere('syncKey.revokedAt IS NULL')
            ->setParameter('keyIdentifier', $keyIdentifier)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$candidate instanceof SyncKey
            || !$candidate->matchesPresentedToken($presentedToken, $this->cipher)) {
            return null;
        }

        return $candidate;
    }
}
