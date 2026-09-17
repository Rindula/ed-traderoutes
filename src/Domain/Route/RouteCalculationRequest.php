<?php

namespace App\Domain\Route;

use App\Entity\Station;
use App\Entity\System;

/**
 * Immutable input for a one-leg A -> B calculation.
 */
final readonly class RouteCalculationRequest
{
    public const DEFAULT_MAX_DATA_AGE_SECONDS = 7200;
    public const ANY_LANDING_CLASS = 'egal';

    private RouteTimeEstimates $timeEstimates;

    /**
     * @param list<string> $allowedStationTypes Empty means all catalogued types.
     */
    public function __construct(
        private System $originSystem,
        private float $shipJumpRange,
        private float $maxJumpDistance,
        private int $cargoCapacity,
        private \DateTimeImmutable $asOf,
        private int $maxDataAgeSeconds = self::DEFAULT_MAX_DATA_AGE_SECONDS,
        private ?string $landingClassFilter = null,
        private array $allowedStationTypes = [],
        private array $illegalCommodities = [],
        private bool $allowIllegalCommodities = false,
        ?RouteTimeEstimates $timeEstimates = null,
    ) {
        if ($this->shipJumpRange <= 0.0 || !is_finite($this->shipJumpRange)) {
            throw new \InvalidArgumentException('shipJumpRange must be a finite positive number.');
        }

        if ($this->maxJumpDistance <= 0.0 || !is_finite($this->maxJumpDistance)) {
            throw new \InvalidArgumentException('maxJumpDistance must be a finite positive number.');
        }

        if ($this->cargoCapacity <= 0) {
            throw new \InvalidArgumentException('cargoCapacity must be positive.');
        }

        if ($this->maxDataAgeSeconds < 0) {
            throw new \InvalidArgumentException('maxDataAgeSeconds must not be negative.');
        }

        if ($this->landingClassFilter === self::ANY_LANDING_CLASS) {
            $this->landingClassFilter = null;
        } elseif ($this->landingClassFilter !== null && !in_array($this->landingClassFilter, [
            Station::LANDING_SMALL,
            Station::LANDING_MEDIUM,
            Station::LANDING_LARGE,
        ], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported landing class filter "%s".', $this->landingClassFilter));
        }

        foreach ($this->allowedStationTypes as $stationType) {
            if (!is_string($stationType) || $stationType === '') {
                throw new \InvalidArgumentException('Allowed station types must be non-empty strings.');
            }
        }
        foreach ($this->illegalCommodities as $commodity) {
            if (!is_string($commodity) || trim($commodity) === '') {
                throw new \InvalidArgumentException('Illegal commodities must be non-empty strings.');
            }
        }

        $this->timeEstimates = $timeEstimates ?? new RouteTimeEstimates();
    }

    public function originSystem(): System
    {
        return $this->originSystem;
    }

    public function shipJumpRange(): float
    {
        return $this->shipJumpRange;
    }

    public function maxJumpDistance(): float
    {
        return $this->maxJumpDistance;
    }

    public function effectiveJumpDistance(): float
    {
        return min($this->shipJumpRange, $this->maxJumpDistance);
    }

    public function cargoCapacity(): int
    {
        return $this->cargoCapacity;
    }

    public function asOf(): \DateTimeImmutable
    {
        return $this->asOf;
    }

    public function maxDataAgeSeconds(): int
    {
        return $this->maxDataAgeSeconds;
    }

    public function landingClassFilter(): ?string
    {
        return $this->landingClassFilter;
    }

    /** @return list<string> */
    public function allowedStationTypes(): array
    {
        return $this->allowedStationTypes;
    }

    public function timeEstimates(): RouteTimeEstimates
    {
        return $this->timeEstimates;
    }

    /** @return list<string> */
    public function illegalCommodities(): array { return $this->illegalCommodities; }

    public function acceptsCommodity(string $commodityName): bool
    {
        return $this->allowIllegalCommodities || !in_array(strtolower($commodityName), array_map('strtolower', $this->illegalCommodities), true);
    }

    public function acceptsStation(Station $station): bool
    {
        if (!$station->hasMarket() || !$station->isAccessible() || $station->getLandingClass() === null) {
            return false;
        }

        if ($this->landingClassFilter !== null && $station->getLandingClass() !== $this->landingClassFilter) {
            return false;
        }

        return $this->allowedStationTypes === []
            || in_array($station->getStationType(), $this->allowedStationTypes, true);
    }
}
