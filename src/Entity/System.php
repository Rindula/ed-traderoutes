<?php

namespace App\Entity;

use App\Repository\SystemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: SystemRepository::class)]
#[ORM\Table(name: 'catalog_system')]
#[ORM\UniqueConstraint(name: 'uniq_catalog_system_name', columns: ['name'])]
class System
{
    /** @var Collection<int, Station> */
    #[ORM\OneToMany(mappedBy: 'system', targetEntity: Station::class)]
    private Collection $stations;

    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $x;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $y;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $z;

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
        string $name,
        string $catalogSource,
        \DateTimeImmutable $catalogObservedAt,
        ?float $x = null,
        ?float $y = null,
        ?float $z = null,
        ?bool $accessible = null,
    ) {
        $this->id = (string) new Ulid();
        $this->stations = new ArrayCollection();
        $this->name = $name;
        $this->catalogSource = $catalogSource;
        $this->catalogObservedAt = $catalogObservedAt;
        $this->updatedAt = new \DateTimeImmutable();
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
        $this->accessible = $accessible;
    }

    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getX(): ?float { return $this->x; }
    public function getY(): ?float { return $this->y; }
    public function getZ(): ?float { return $this->z; }
    public function getAccessible(): ?bool { return $this->accessible; }
    public function isAccessible(): bool { return $this->accessible === true; }
    public function getCatalogSource(): string { return $this->catalogSource; }
    public function getCatalogObservedAt(): \DateTimeImmutable { return $this->catalogObservedAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    /** @return Collection<int, Station> */
    public function getStations(): Collection { return $this->stations; }

    public function updateCatalogData(
        string $catalogSource,
        \DateTimeImmutable $catalogObservedAt,
        ?float $x = null,
        ?float $y = null,
        ?float $z = null,
        ?bool $accessible = null,
    ): void {
        $this->catalogSource = $catalogSource;
        $this->catalogObservedAt = $catalogObservedAt;
        $this->updatedAt = new \DateTimeImmutable();
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
        $this->accessible = $accessible;
    }

    public function addStation(Station $station): void
    {
        if (!$this->stations->contains($station)) {
            $this->stations->add($station);
        }
    }

    public function removeStation(Station $station): void
    {
        $this->stations->removeElement($station);
    }
}
