<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(
    name: 'plugin_status',
    uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_plugin_status_user', columns: ['user_id'])],
    indexes: [new ORM\Index(name: 'idx_plugin_status_heartbeat', columns: ['last_heartbeat_at'])],
)]
class PluginStatus
{
    public const MODE_ACTIVE = 'active';
    public const MODE_MANUAL = 'manual';

    public const DEFAULT_HEARTBEAT_INTERVAL_SECONDS = 60;
    public const MISSED_HEARTBEAT_THRESHOLD = 3;

    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $id;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 16)]
    private string $mode;

    #[ORM\Column(name: 'last_heartbeat_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastHeartbeatAt = null;

    public function __construct(User $user, ?\DateTimeImmutable $lastHeartbeatAt = null)
    {
        $this->id = (string) new Ulid();
        $this->user = $user;
        $this->mode = self::MODE_MANUAL;

        if ($lastHeartbeatAt !== null) {
            $this->receiveHeartbeat($lastHeartbeatAt);
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function isActive(): bool
    {
        return $this->mode === self::MODE_ACTIVE;
    }

    public function isManual(): bool
    {
        return $this->mode === self::MODE_MANUAL;
    }

    public function getLastHeartbeatAt(): ?\DateTimeImmutable
    {
        return $this->lastHeartbeatAt;
    }

    /** A valid heartbeat restores automatic mode for the user. */
    public function receiveHeartbeat(?\DateTimeImmutable $receivedAt = null): void
    {
        $this->lastHeartbeatAt = $receivedAt ?? new \DateTimeImmutable();
        $this->mode = self::MODE_ACTIVE;
    }

    /**
     * Returns the number of heartbeat intervals that have elapsed since the
     * last heartbeat. A status without a heartbeat has missed the threshold.
     */
    public function missedHeartbeatsAt(
        \DateTimeImmutable $at,
        int $heartbeatIntervalSeconds = self::DEFAULT_HEARTBEAT_INTERVAL_SECONDS,
    ): int {
        if ($heartbeatIntervalSeconds <= 0) {
            throw new \InvalidArgumentException('The heartbeat interval must be positive.');
        }

        if ($this->lastHeartbeatAt === null || $at <= $this->lastHeartbeatAt) {
            return $this->lastHeartbeatAt === null ? self::MISSED_HEARTBEAT_THRESHOLD : 0;
        }

        return intdiv($at->getTimestamp() - $this->lastHeartbeatAt->getTimestamp(), $heartbeatIntervalSeconds);
    }

    public function isHeartbeatExpiredAt(
        \DateTimeImmutable $at,
        int $heartbeatIntervalSeconds = self::DEFAULT_HEARTBEAT_INTERVAL_SECONDS,
    ): bool {
        return $this->missedHeartbeatsAt($at, $heartbeatIntervalSeconds) >= self::MISSED_HEARTBEAT_THRESHOLD;
    }

    /**
     * Applies the fail-safe mode transition after three missed heartbeats.
     * It deliberately does not reactivate a plugin; only a new heartbeat may do so.
     */
    public function refreshModeAt(
        \DateTimeImmutable $at,
        int $heartbeatIntervalSeconds = self::DEFAULT_HEARTBEAT_INTERVAL_SECONDS,
    ): void {
        if ($this->isHeartbeatExpiredAt($at, $heartbeatIntervalSeconds)) {
            $this->switchToManualMode();
        }
    }

    public function switchToManualMode(): void
    {
        $this->mode = self::MODE_MANUAL;
    }

    public function isAutomaticModeAt(
        \DateTimeImmutable $at,
        int $heartbeatIntervalSeconds = self::DEFAULT_HEARTBEAT_INTERVAL_SECONDS,
    ): bool {
        return $this->isActive() && !$this->isHeartbeatExpiredAt($at, $heartbeatIntervalSeconds);
    }
}
