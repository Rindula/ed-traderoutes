<?php

namespace App\Entity;

use App\Repository\StationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: StationRepository::class)]
#[ORM\Table(
    name: 'catalog_station',
    uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_catalog_station_system_name', columns: ['system_id', 'name'])],
    indexes: [new ORM\Index(name: 'idx_catalog_station_filter', columns: ['landing_class', 'station_type', 'accessible'])],
)]
class Station
{
    public const TYPE_ORBITAL = 'orbital';
    public const TYPE_OUTPOST = 'outpost';
    public const TYPE_PLANETARY_PORT = 'planetary_port';
    public const TYPE_FLEET_CARRIER = 'fleet_carrier';
    public const TYPE_MEGASHIP = 'megaship';
    public const TYPE_SPECIAL_MARKET = 'special_market';

    public const LANDING_SMALL = 'small';
    public const LANDING_MEDIUM = 'medium';
    public const LANDING_LARGE = 'large';

    /** @var Collection<int, MarketObservation> */
    #[ORM\OneToMany(mappedBy: 'station', targetEntity: MarketObservation::class)]
    private Collection $marketObservations;

    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: System::class, inversedBy: 'stations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private System $system;

    #[ORM\Column(length: 255)]
    private string $name;

    /** Stable market identifier supplied by the game, when available. */
    #[ORM\Column(name: 'market_id', type: 'bigint', nullable: true, unique: true)]
    private ?string $marketId;

    #[ORM\Column(name: 'station_type', length: 64)]
    private string $stationType;

    /** Null means that the catalog has no reliable landing information. */
    #[ORM\Column(name: 'landing_class', length: 16, nullable: true)]
    private ?string $landingClass;

    #[ORM\Column(type: 'boolean')]
    private bool $hasMarket;

    /** Null means that the catalog has not established accessibility. */
    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $accessible;

    #[ORM\Column(length: 64)]
    private string $catalogSource;

    #[ORM\Column]
    private \DateTimeImmutable $catalogObservedAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        System $system,
        string $name,
        string $stationType,
        string $catalogSource,
        \DateTimeImmutable $catalogObservedAt,
        ?string $marketId = null,
        ?string $landingClass = null,
        bool $hasMarket = true,
        ?bool $accessible = null,
    ) {
        $this->id = (string) new Ulid();
        $this->marketObservations = new ArrayCollection();
        $this->system = $system;
        $this->name = $name;
        $this->stationType = $stationType;
        $this->catalogSource = $catalogSource;
        $this->catalogObservedAt = $catalogObservedAt;
        $this->updatedAt = new \DateTimeImmutable();
        $this->marketId = $marketId;
        $this->landingClass = $landingClass;
        $this->hasMarket = $hasMarket;
        $this->accessible = $accessible;
        $system->addStation($this);
    }

    public function getId(): string { return $this->id; }
    public function getSystem(): System { return $this->system; }
    public function getName(): string { return $this->name; }
    public function getMarketId(): ?string { return $this->marketId; }
    public function getStationType(): string { return $this->stationType; }
    public function getLandingClass(): ?string { return $this->landingClass; }
    public function hasMarket(): bool { return $this->hasMarket; }
    public function getAccessible(): ?bool { return $this->accessible; }
    public function isAccessible(): bool { return $this->accessible === true; }
    public function getCatalogSource(): string { return $this->catalogSource; }
    public function getCatalogObservedAt(): \DateTimeImmutable { return $this->catalogObservedAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    /** @return Collection<int, MarketObservation> */
    public function getMarketObservations(): Collection { return $this->marketObservations; }

    public function updateCatalogData(
        string $stationType,
        string $catalogSource,
        \DateTimeImmutable $catalogObservedAt,
        ?string $marketId = null,
        ?string $landingClass = null,
        bool $hasMarket = true,
        ?bool $accessible = null,
    ): void {
        $this->stationType = $stationType;
        $this->catalogSource = $catalogSource;
        $this->catalogObservedAt = $catalogObservedAt;
        $this->updatedAt = new \DateTimeImmutable();
        $this->marketId = $marketId;
        $this->landingClass = $landingClass;
        $this->hasMarket = $hasMarket;
        $this->accessible = $accessible;
    }

    public function addMarketObservation(MarketObservation $observation): void
    {
        if (!$this->marketObservations->contains($observation)) {
            $this->marketObservations->add($observation);
        }
    }

    public function removeMarketObservation(MarketObservation $observation): void
    {
        $this->marketObservations->removeElement($observation);
    }
}
