<?php

namespace App\Domain\Route;

use App\Entity\Station;

/**
 * Deterministic duration assumptions for a single trade leg.
 */
final readonly class RouteTimeEstimates
{
    /**
     * @param array<string, float|int> $secondsByStationType
     */
    public function __construct(
        private float $secondsPerJump = 45.0,
        private float $secondsPerTrade = 30.0,
        private array $secondsByStationType = [],
        private float $defaultStationSeconds = 120.0,
    ) {
        self::assertPositive($this->secondsPerJump, 'secondsPerJump');
        self::assertPositive($this->secondsPerTrade, 'secondsPerTrade');
        self::assertNonNegative($this->defaultStationSeconds, 'defaultStationSeconds');

        foreach ($this->secondsByStationType as $stationType => $seconds) {
            if (!is_string($stationType) || $stationType === '') {
                throw new \InvalidArgumentException('Station time keys must be non-empty strings.');
            }

            if (!is_int($seconds) && !is_float($seconds)) {
                throw new \InvalidArgumentException(sprintf('Station time for "%s" must be numeric.', $stationType));
            }

            self::assertNonNegative((float) $seconds, sprintf('secondsByStationType[%s]', $stationType));
        }
    }

    public function secondsPerJump(): float
    {
        return $this->secondsPerJump;
    }

    public function secondsPerTrade(): float
    {
        return $this->secondsPerTrade;
    }

    public function secondsForStation(string $stationType): float
    {
        return (float) ($this->secondsByStationType[$stationType] ?? $this->defaultStationSeconds);
    }

    public function secondsFor(Station $station): float
    {
        return $this->secondsForStation($station->getStationType());
    }

    private static function assertPositive(float $value, string $name): void
    {
        if (!is_finite($value) || $value <= 0.0) {
            throw new \InvalidArgumentException(sprintf('%s must be a finite positive number.', $name));
        }
    }

    private static function assertNonNegative(float $value, string $name): void
    {
        if (!is_finite($value) || $value < 0.0) {
            throw new \InvalidArgumentException(sprintf('%s must be a finite non-negative number.', $name));
        }
    }
}
