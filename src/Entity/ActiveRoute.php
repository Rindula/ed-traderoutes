<?php

namespace App\Entity;

use App\Repository\ActiveRouteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: ActiveRouteRepository::class)]
#[ORM\Table(
    name: 'active_route',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_active_route_user', columns: ['user_id']),
    ],
    indexes: [
        new ORM\Index(name: 'idx_active_route_bound', columns: ['current_leg_bound']),
    ],
)]
final class ActiveRoute
{
    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $id;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'route_identifier', length: 255)]
    private string $routeIdentifier;

    /**
     * Ordered route-leg snapshots. Each item is kept as an opaque leg payload
     * so the active route can be persisted without coupling this aggregate to
     * the route planner's in-memory types.
     *
     * @var list<array<string, mixed>>
     */
    #[ORM\Column(type: 'json')]
    private array $legs;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $alternatives = [];

    #[ORM\Column(name: 'current_leg_index', type: 'integer')]
    private int $currentLegIndex;

    #[ORM\Column(name: 'current_leg_bound', type: 'boolean')]
    private bool $currentLegBound;

    #[ORM\Column(name: 'bound_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $boundAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param list<array<string, mixed>> $legs
     */
    public function __construct(
        User $user,
        string $routeIdentifier,
        array $legs,
        ?\DateTimeImmutable $updatedAt = null,
    ) {
        self::assertRouteIdentifier($routeIdentifier);

        $this->id = (string) new Ulid();
        $this->user = $user;
        $this->routeIdentifier = $routeIdentifier;
        $this->legs = self::normalizeLegs($legs);
        $this->currentLegIndex = 0;
        $this->currentLegBound = false;
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getRouteIdentifier(): string
    {
        return $this->routeIdentifier;
    }

    /** @return list<array<string, mixed>> */
    public function getLegs(): array
    {
        return $this->legs;
    }

    public function getCurrentLegIndex(): int
    {
        return $this->currentLegIndex;
    }

    /** @return array<string, mixed>|null */
    public function getCurrentLeg(): ?array
    {
        return $this->legs[$this->currentLegIndex] ?? null;
    }

    public function isCompleted(): bool
    {
        return $this->currentLegIndex >= count($this->legs);
    }

    public function isCurrentLegBound(): bool
    {
        return $this->currentLegBound;
    }

    public function hasBoundLeg(): bool
    {
        return $this->currentLegBound;
    }

    public function getBoundAt(): ?\DateTimeImmutable
    {
        return $this->boundAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return list<array<string, mixed>> */
    public function getAlternatives(): array { return $this->alternatives; }

    /** @param list<array<string, mixed>> $alternatives */
    public function setAlternatives(array $alternatives): void
    {
        $this->alternatives = array_values(array_filter($alternatives, 'is_array'));
        $this->touch(null);
    }

    /**
     * Replace the selected route while no cargo commitment exists.
     *
     * A purchase binds the current leg. Once bound, even an alternative route
     * cannot replace it; callers may only replace following legs through
     * replaceFollowingLegs().
     *
     * @param list<array<string, mixed>> $legs
     */
    public function replaceRoute(
        string $routeIdentifier,
        array $legs,
        ?\DateTimeImmutable $updatedAt = null,
    ): void {
        self::assertRouteIdentifier($routeIdentifier);

        if ($this->currentLegBound) {
            throw new \LogicException('The active leg is bound and cannot be replaced.');
        }

        $this->routeIdentifier = $routeIdentifier;
        $this->legs = self::normalizeLegs($legs);
        $this->alternatives = [];
        $this->currentLegIndex = 0;
        $this->boundAt = null;
        $this->touch($updatedAt);
    }

    /**
     * Replace only the route suffix after the current leg.
     *
     * This is the recalculation seam: a bound current leg remains byte-for-
     * byte unchanged while later legs may be replanned.
     *
     * @param list<array<string, mixed>> $followingLegs
     */
    public function replaceFollowingLegs(
        array $followingLegs,
        ?\DateTimeImmutable $updatedAt = null,
    ): void {
        $followingLegs = self::normalizeLegs($followingLegs, allowEmpty: true);
        $prefix = array_slice($this->legs, 0, $this->currentLegIndex + 1);
        $this->legs = [...$prefix, ...$followingLegs];
        $this->alternatives = [];
        $this->touch($updatedAt);
    }

    /** Bind the current leg at the confirmed purchase boundary. */
    public function bindCurrentLeg(?\DateTimeImmutable $boundAt = null): void
    {
        if ($this->isCompleted()) {
            throw new \LogicException('A completed route has no current leg to bind.');
        }

        $this->currentLegBound = true;
        $this->boundAt ??= $boundAt ?? new \DateTimeImmutable();
        $this->touch($boundAt);
    }

    /**
     * Complete the bound leg after the confirmed sale/completion event.
     *
     * The next leg becomes active but remains unbound until its purchase is
     * confirmed. No method exists to clear a binding without advancing the
     * route, preventing an accidental unlock of purchased cargo.
     */
    public function completeCurrentLeg(?\DateTimeImmutable $completedAt = null): void
    {
        if ($this->isCompleted()) {
            throw new \LogicException('The active route is already completed.');
        }

        if (!$this->currentLegBound) {
            throw new \LogicException('The current leg must be bound before completion.');
        }

        $this->currentLegIndex++;
        $this->currentLegBound = false;
        $this->boundAt = null;
        $this->touch($completedAt);
    }

    /** @param list<array<string, mixed>> $legs */
    private static function normalizeLegs(array $legs, bool $allowEmpty = false): array
    {
        if (!$allowEmpty && $legs === []) {
            throw new \InvalidArgumentException('An active route must contain at least one leg.');
        }

        if (!array_is_list($legs)) {
            throw new \InvalidArgumentException('Route legs must be provided as an ordered list.');
        }

        foreach ($legs as $leg) {
            if (!is_array($leg)) {
                throw new \InvalidArgumentException('Every route leg must be an array payload.');
            }
        }

        return array_values($legs);
    }

    private static function assertRouteIdentifier(string $routeIdentifier): void
    {
        if ($routeIdentifier === '') {
            throw new \InvalidArgumentException('The route identifier must not be empty.');
        }
    }

    private function touch(?\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
    }
}
