<?php

namespace App\Entity;

use App\Repository\MarketObservationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: MarketObservationRepository::class)]
#[ORM\Table(
    name: 'market_observation',
    indexes: [
        new ORM\Index(name: 'idx_market_observation_current', columns: ['station_id', 'commodity_name', 'observed_at']),
        new ORM\Index(name: 'idx_market_observation_freshness', columns: ['observed_at']),
    ],
)]
class MarketObservation
{
    public const SOURCE_EDDN = 'eddn';
    public const SOURCE_EDMC = 'edmc';

    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Station::class, inversedBy: 'marketObservations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Station $station;

    #[ORM\Column(length: 255)]
    private string $commodityName;

    #[ORM\Column(length: 16)]
    private string $source;

    #[ORM\Column]
    private \DateTimeImmutable $observedAt;

    #[ORM\Column]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $buyPrice;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $sellPrice;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $stock;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $demand;

    /** Optional source-side identifier for idempotent ingestion. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceReference;

    public function __construct(
        Station $station,
        string $commodityName,
        string $source,
        \DateTimeImmutable $observedAt,
        ?int $buyPrice = null,
        ?int $sellPrice = null,
        ?int $stock = null,
        ?int $demand = null,
        ?string $sourceReference = null,
        ?\DateTimeImmutable $receivedAt = null,
    ) {
        if (!in_array($source, [self::SOURCE_EDDN, self::SOURCE_EDMC], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported market observation source "%s".', $source));
        }

        $this->id = (string) new Ulid();
        $this->station = $station;
        $this->commodityName = $commodityName;
        $this->source = $source;
        $this->observedAt = $observedAt;
        $this->receivedAt = $receivedAt ?? new \DateTimeImmutable();
        $this->buyPrice = $buyPrice;
        $this->sellPrice = $sellPrice;
        $this->stock = $stock;
        $this->demand = $demand;
        $this->sourceReference = $sourceReference;
        $station->addMarketObservation($this);
    }

    public function getId(): string { return $this->id; }
    public function getStation(): Station { return $this->station; }
    public function getCommodityName(): string { return $this->commodityName; }
    public function getSource(): string { return $this->source; }
    public function getObservedAt(): \DateTimeImmutable { return $this->observedAt; }
    public function getReceivedAt(): \DateTimeImmutable { return $this->receivedAt; }
    public function getBuyPrice(): ?int { return $this->buyPrice; }
    public function getSellPrice(): ?int { return $this->sellPrice; }
    public function getStock(): ?int { return $this->stock; }
    public function getDemand(): ?int { return $this->demand; }
    public function getSourceReference(): ?string { return $this->sourceReference; }

    public function isFreshAt(\DateTimeImmutable $asOf, int $maxAgeSeconds): bool
    {
        if ($maxAgeSeconds < 0 || $this->observedAt > $asOf) {
            return false;
        }

        return $this->observedAt >= $asOf->modify(sprintf('-%d seconds', $maxAgeSeconds));
    }

    public function isNewerThan(self $other): bool
    {
        if ($this->observedAt != $other->observedAt) {
            return $this->observedAt > $other->observedAt;
        }

        if ($this->receivedAt != $other->receivedAt) {
            return $this->receivedAt > $other->receivedAt;
        }

        return $this->id > $other->id;
    }

    /** Null means supply or demand is unknown and the quantity is not reliable. */
    public function getTradeableQuantity(): ?int
    {
        if ($this->stock === null || $this->demand === null) {
            return null;
        }

        return min(max(0, $this->stock), max(0, $this->demand));
    }

    public function isTradeable(): bool
    {
        $quantity = $this->getTradeableQuantity();

        return $this->buyPrice !== null
            && $this->buyPrice > 0
            && $this->sellPrice !== null
            && $this->sellPrice > 0
            && $quantity !== null
            && $quantity > 0;
    }

    public function updateValues(
        \DateTimeImmutable $observedAt,
        ?int $buyPrice = null,
        ?int $sellPrice = null,
        ?int $stock = null,
        ?int $demand = null,
        ?string $sourceReference = null,
    ): void {
        $this->observedAt = $observedAt;
        $this->receivedAt = new \DateTimeImmutable();
        $this->buyPrice = $buyPrice;
        $this->sellPrice = $sellPrice;
        $this->stock = $stock;
        $this->demand = $demand;
        $this->sourceReference = $sourceReference;
    }
}
