<?php

namespace App\Entity;

use App\Repository\SyncEventRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: SyncEventRepository::class)]
#[ORM\Table(
    name: 'sync_event',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_sync_event_user_external_id', columns: ['user_id', 'external_event_id']),
    ],
    indexes: [
        new ORM\Index(name: 'idx_sync_event_user_sequence', columns: ['user_id', 'sequence_number']),
        new ORM\Index(name: 'idx_sync_event_source_timestamp', columns: ['source_timestamp']),
    ],
)]
class SyncEvent
{
    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** Stable plugin-side identity used to make retries idempotent per user. */
    #[ORM\Column(name: 'external_event_id', length: 255)]
    private string $externalEventId;

    #[ORM\Column(name: 'sequence_number', type: 'integer')]
    private int $sequence;

    #[ORM\Column(name: 'event_type', length: 64)]
    private string $eventType;

    #[ORM\Column(name: 'source_timestamp', type: 'datetime_immutable')]
    private \DateTimeImmutable $sourceTimestamp;

    #[ORM\Column(name: 'received_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $receivedAt;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        User $user,
        string $externalEventId,
        int $sequence,
        string $eventType,
        \DateTimeImmutable $sourceTimestamp,
        array $payload = [],
        ?\DateTimeImmutable $receivedAt = null,
    ) {
        if ($externalEventId === '') {
            throw new \InvalidArgumentException('The external event id must not be empty.');
        }

        if ($sequence < 0) {
            throw new \InvalidArgumentException('The event sequence must not be negative.');
        }

        if ($eventType === '') {
            throw new \InvalidArgumentException('The event type must not be empty.');
        }

        $this->id = (string) new Ulid();
        $this->user = $user;
        $this->externalEventId = $externalEventId;
        $this->sequence = $sequence;
        $this->eventType = $eventType;
        $this->sourceTimestamp = $sourceTimestamp;
        $this->receivedAt = $receivedAt ?? new \DateTimeImmutable();
        $this->payload = $payload;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getExternalEventId(): string
    {
        return $this->externalEventId;
    }

    public function getSequence(): int
    {
        return $this->sequence;
    }

    public function getSequenceNumber(): int
    {
        return $this->sequence;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getSourceTimestamp(): \DateTimeImmutable
    {
        return $this->sourceTimestamp;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * The database uniqueness boundary is user plus plugin event id.
     */
    public function hasSameIdempotencyIdentityAs(self $other): bool
    {
        return $this->user->getId() === $other->user->getId()
            && $this->externalEventId === $other->externalEventId;
    }

    public function getIdempotencyKey(): string
    {
        return $this->user->getId().':'.$this->externalEventId;
    }

    /** A retry with the same identity is safe only if its content is unchanged. */
    public function isEquivalentTo(self $other): bool
    {
        return $this->hasSameIdempotencyIdentityAs($other)
            && $this->sequence === $other->sequence
            && $this->eventType === $other->eventType
            && $this->sourceTimestamp == $other->sourceTimestamp
            && $this->payload === $other->payload;
    }

    public function isConflictingDuplicateOf(self $other): bool
    {
        return $this->hasSameIdempotencyIdentityAs($other) && !$this->isEquivalentTo($other);
    }

    /**
     * A missing, repeated, reordered, or timestamp-regressing sequence is
     * uncertain. The first event is valid only when it starts at sequence 0 or 1.
     * A retry with the same identity is not a sequence gap.
     */
    public function hasUncertainSequenceAfter(?self $previous): bool
    {
        if ($previous === null) {
            return $this->sequence > 1;
        }

        if ($this->hasSameIdempotencyIdentityAs($previous)) {
            return $this->isConflictingDuplicateOf($previous);
        }

        return $this->user->getId() !== $previous->user->getId()
            || $this->sequence !== $previous->sequence + 1
            || $this->sourceTimestamp < $previous->sourceTimestamp;
    }

    public function isContiguousAfter(?self $previous): bool
    {
        return !$this->hasUncertainSequenceAfter($previous);
    }
}
