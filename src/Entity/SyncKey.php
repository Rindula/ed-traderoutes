<?php

namespace App\Entity;

use App\Repository\SyncKeyRepository;
use App\Security\SyncKeyCipher;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: SyncKeyRepository::class)]
#[ORM\Table(
    name: 'sync_key',
    indexes: [
        new ORM\Index(name: 'idx_sync_key_user_active', columns: ['user_id', 'revoked_at']),
    ],
)]
final class SyncKey
{
    private const TOKEN_BYTES = 32;
    private const MASK_VISIBLE_CHARACTERS = 4;

    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** The encrypted value is deliberately never exposed as a public API value. */
    #[ORM\Column(name: 'encrypted_token', type: 'text')]
    private string $encryptedToken;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_activity_at', nullable: true)]
    private ?\DateTimeImmutable $lastActivityAt = null;

    #[ORM\Column(name: 'revoked_at', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    private function __construct(
        User $user,
        string $keyIdentifier,
        string $encryptedToken,
        \DateTimeImmutable $createdAt,
    ) {
        if ($keyIdentifier === '') {
            throw new \InvalidArgumentException('A synchronization key identifier is required.');
        }

        if ($encryptedToken === '') {
            throw new \InvalidArgumentException('An encrypted synchronization token is required.');
        }

        $this->id = $keyIdentifier;
        $this->user = $user;
        $this->encryptedToken = $encryptedToken;
        $this->createdAt = $createdAt;
    }

    public static function create(
        User $user,
        SyncKeyCipher $cipher,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        $rawToken = bin2hex(random_bytes(self::TOKEN_BYTES));

        return new self(
            $user,
            (string) new Ulid(),
            $cipher->encrypt($rawToken),
            $createdAt ?? new \DateTimeImmutable(),
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    /** Stable identifier sent alongside the raw token by the EDMC plugin. */
    public function getKeyIdentifier(): string
    {
        return $this->id;
    }

    public function getIdentifier(): string
    {
        return $this->getKeyIdentifier();
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastActivityAt(): ?\DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function revoke(?\DateTimeImmutable $revokedAt = null): void
    {
        $this->revokedAt ??= $revokedAt ?? new \DateTimeImmutable();
    }

    public function markActivity(?\DateTimeImmutable $activityAt = null): void
    {
        $activityAt ??= new \DateTimeImmutable();

        if ($this->lastActivityAt === null || $activityAt > $this->lastActivityAt) {
            $this->lastActivityAt = $activityAt;
        }
    }

    /** Reveal only at an already authorized application boundary. */
    public function revealToken(SyncKeyCipher $cipher): string
    {
        if ($this->isRevoked()) {
            throw new \LogicException('A revoked synchronization key cannot be revealed.');
        }

        return $cipher->decrypt($this->encryptedToken);
    }

    public function getMaskedToken(SyncKeyCipher $cipher): string
    {
        return self::maskToken($this->revealToken($cipher));
    }

    /**
     * Compare a presented token without exposing the encrypted database value.
     * Hashing both values gives hash_equals fixed-size input for every candidate.
     */
    public function matchesPresentedToken(string $presentedToken, SyncKeyCipher $cipher): bool
    {
        try {
            $storedToken = $this->revealToken($cipher);
        } catch (\Throwable) {
            return false;
        }

        return hash_equals(
            hash('sha256', $storedToken, true),
            hash('sha256', $presentedToken, true),
        );
    }

    private static function maskToken(string $token): string
    {
        $length = strlen($token);
        if ($length <= self::MASK_VISIBLE_CHARACTERS * 2) {
            return str_repeat('*', $length);
        }

        return substr($token, 0, self::MASK_VISIBLE_CHARACTERS)
            .str_repeat('*', $length - self::MASK_VISIBLE_CHARACTERS * 2)
            .substr($token, -self::MASK_VISIBLE_CHARACTERS);
    }
}
