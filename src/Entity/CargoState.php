<?php

namespace App\Entity;

use App\Repository\CargoStateRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: CargoStateRepository::class)]
#[ORM\Table(
    name: 'cargo_state',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_cargo_state_user', columns: ['user_id']),
    ],
    indexes: [
        new ORM\Index(name: 'idx_cargo_state_bound_leg', columns: ['bound_route_identifier', 'bound_leg_identifier']),
        new ORM\Index(name: 'idx_cargo_state_uncertain', columns: ['uncertain']),
    ],
)]
final class CargoState
{
    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $id;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** @var array<string, int> Commodity name to quantity. */
    #[ORM\Column(type: 'json')]
    private array $cargo;

    #[ORM\Column(name: 'cargo_capacity', type: 'integer')]
    private int $cargoCapacity;

    #[ORM\Column(type: 'boolean')]
    private bool $uncertain;

    /** Stable identifier of the route containing the currently bound leg. */
    #[ORM\Column(name: 'bound_route_identifier', length: 255, nullable: true)]
    private ?string $boundRouteIdentifier = null;

    /** Stable identifier of the currently bound leg within that route. */
    #[ORM\Column(name: 'bound_leg_identifier', length: 255, nullable: true)]
    private ?string $boundLegIdentifier = null;

    #[ORM\Column(name: 'bound_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $boundAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param array<string, int> $cargo
     */
    public function __construct(
        User $user,
        int $cargoCapacity,
        array $cargo = [],
        bool $uncertain = false,
        ?\DateTimeImmutable $updatedAt = null,
    ) {
        if ($cargoCapacity < 0) {
            throw new \InvalidArgumentException('The cargo capacity must not be negative.');
        }

        $this->id = (string) new Ulid();
        $this->user = $user;
        $this->cargoCapacity = $cargoCapacity;
        $this->cargo = self::normalizeCargo($cargo);
        $this->uncertain = $uncertain;
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();

        $this->assertWithinCapacity();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /** @return array<string, int> */
    public function getCargo(): array
    {
        return $this->cargo;
    }

    public function getQuantity(string $commodityName): int
    {
        return $this->cargo[$commodityName] ?? 0;
    }

    public function getCargoCapacity(): int
    {
        return $this->cargoCapacity;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getUsedCapacity(): int
    {
        return array_sum($this->cargo);
    }

    public function getRemainingCapacity(): int
    {
        return $this->cargoCapacity - $this->getUsedCapacity();
    }

    public function isUncertain(): bool
    {
        return $this->uncertain;
    }

    public function markUncertain(?\DateTimeImmutable $updatedAt = null): void
    {
        $this->uncertain = true;
        $this->touch($updatedAt);
    }

    public function confirm(?\DateTimeImmutable $updatedAt = null): void
    {
        $this->uncertain = false;
        $this->touch($updatedAt);
    }

    /**
     * Replace the complete synchronized cargo inventory.
     *
     * @param array<string, int> $cargo
     */
    public function replaceCargo(array $cargo, ?\DateTimeImmutable $updatedAt = null): void
    {
        $this->cargo = self::normalizeCargo($cargo);
        $this->assertWithinCapacity();
        $this->touch($updatedAt);
    }

    public function getBoundRouteIdentifier(): ?string
    {
        return $this->boundRouteIdentifier;
    }

    public function getBoundLegIdentifier(): ?string
    {
        return $this->boundLegIdentifier;
    }

    public function getBoundAt(): ?\DateTimeImmutable
    {
        return $this->boundAt;
    }

    public function hasBoundLeg(): bool
    {
        return $this->boundRouteIdentifier !== null && $this->boundLegIdentifier !== null;
    }

    public function isBoundToLeg(string $routeIdentifier, string $legIdentifier): bool
    {
        return $this->boundRouteIdentifier === $routeIdentifier
            && $this->boundLegIdentifier === $legIdentifier;
    }

    /**
     * Bind the current cargo to the purchased route leg.
     *
     * Rebinding to another leg is deliberately rejected while a binding
     * exists; completion or sale must clear it first.
     */
    public function bindToLeg(
        string $routeIdentifier,
        string $legIdentifier,
        ?\DateTimeImmutable $boundAt = null,
    ): void {
        self::assertIdentifier($routeIdentifier, 'route');
        self::assertIdentifier($legIdentifier, 'leg');

        if ($this->hasBoundLeg() && !$this->isBoundToLeg($routeIdentifier, $legIdentifier)) {
            throw new \LogicException('Cargo is already bound to another route leg.');
        }

        $this->boundRouteIdentifier = $routeIdentifier;
        $this->boundLegIdentifier = $legIdentifier;
        $this->boundAt ??= $boundAt ?? new \DateTimeImmutable();
        $this->touch($boundAt);
    }

    /** Release the binding after a confirmed sale or leg completion. */
    public function clearBoundLeg(?\DateTimeImmutable $updatedAt = null): void
    {
        $this->boundRouteIdentifier = null;
        $this->boundLegIdentifier = null;
        $this->boundAt = null;
        $this->touch($updatedAt);
    }

    /** @param array<string, int> $cargo */
    private static function normalizeCargo(array $cargo): array
    {
        $normalized = [];

        foreach ($cargo as $commodityName => $quantity) {
            if (!is_string($commodityName) || $commodityName === '') {
                throw new \InvalidArgumentException('Every cargo item needs a commodity name.');
            }

            if (!is_int($quantity) || $quantity < 0) {
                throw new \InvalidArgumentException('Cargo quantities must be non-negative integers.');
            }

            if ($quantity > 0) {
                $normalized[$commodityName] = $quantity;
            }
        }

        ksort($normalized);

        return $normalized;
    }

    private function assertWithinCapacity(): void
    {
        if ($this->getUsedCapacity() > $this->cargoCapacity) {
            throw new \InvalidArgumentException('The cargo inventory exceeds the cargo capacity.');
        }
    }

    private static function assertIdentifier(string $identifier, string $type): void
    {
        if ($identifier === '') {
            throw new \InvalidArgumentException(sprintf('The %s identifier must not be empty.', $type));
        }
    }

    private function touch(?\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
    }
}
