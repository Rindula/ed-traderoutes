<?php

namespace App\Entity;

use App\Repository\RouteSnapshotRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: RouteSnapshotRepository::class)]
#[ORM\Table(
    name: 'route_snapshot',
    indexes: [
        new ORM\Index(name: 'idx_route_snapshot_user_calculated', columns: ['user_id', 'calculated_at']),
    ],
)]
final class RouteSnapshot
{
    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** Stable identifier for the selected route calculation. */
    #[ORM\Column(name: 'route_identifier', length: 255)]
    private string $routeIdentifier;

    #[ORM\ManyToOne(targetEntity: Station::class)]
    #[ORM\JoinColumn(name: 'source_station_id', nullable: false, onDelete: 'CASCADE')]
    private Station $sourceStation;

    #[ORM\ManyToOne(targetEntity: Station::class)]
    #[ORM\JoinColumn(name: 'target_station_id', nullable: false, onDelete: 'CASCADE')]
    private Station $targetStation;

    #[ORM\Column(name: 'commodity_name', length: 255)]
    private string $commodityName;

    #[ORM\Column(type: 'integer')]
    private int $quantity;

    #[ORM\Column(name: 'expected_profit_per_hour', type: 'bigint')]
    private int $expectedProfitPerHour;

    #[ORM\Column(name: 'calculated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $calculatedAt;

    public function __construct(
        User $user,
        string $routeIdentifier,
        Station $sourceStation,
        Station $targetStation,
        string $commodityName,
        int $quantity,
        int $expectedProfitPerHour,
        ?\DateTimeImmutable $calculatedAt = null,
    ) {
        if ($routeIdentifier === '') {
            throw new \InvalidArgumentException('The route identifier must not be empty.');
        }

        if ($commodityName === '') {
            throw new \InvalidArgumentException('The commodity name must not be empty.');
        }

        if ($quantity < 0) {
            throw new \InvalidArgumentException('The route quantity must not be negative.');
        }

        $this->id = (string) new Ulid();
        $this->user = $user;
        $this->routeIdentifier = $routeIdentifier;
        $this->sourceStation = $sourceStation;
        $this->targetStation = $targetStation;
        $this->commodityName = $commodityName;
        $this->quantity = $quantity;
        $this->expectedProfitPerHour = $expectedProfitPerHour;
        $this->calculatedAt = $calculatedAt ?? new \DateTimeImmutable();
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

    public function getSourceStation(): Station
    {
        return $this->sourceStation;
    }

    public function getTargetStation(): Station
    {
        return $this->targetStation;
    }

    public function getCommodityName(): string
    {
        return $this->commodityName;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getExpectedProfitPerHour(): int
    {
        return $this->expectedProfitPerHour;
    }

    public function getCalculatedAt(): \DateTimeImmutable
    {
        return $this->calculatedAt;
    }
}
