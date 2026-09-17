<?php

namespace App\Domain\Route;

use App\Domain\Cargo\CargoManifest;
use App\Entity\Station;

/**
 * Ordered, executable route result with its resulting cargo state.
 */
final readonly class MultiLegRoute
{
    /** @var list<MultiLegRouteLeg> */
    private array $legs;

    private CargoManifest $currentCargo;

    /**
     * @param list<MultiLegRouteLeg> $legs
     */
    public function __construct(array $legs, CargoManifest $currentCargo, private bool $closed = false)
    {
        if ($legs === []) {
            throw new \InvalidArgumentException('A multi-leg route must contain at least one leg.');
        }

        foreach ($legs as $leg) {
            if (!$leg instanceof MultiLegRouteLeg) {
                throw new \InvalidArgumentException('Route legs must be MultiLegRouteLeg instances.');
            }
        }

        $normalizedLegs = [];
        foreach (array_values($legs) as $index => $leg) {
            if ($index > 0) {
                $previousCargo = $normalizedLegs[$index - 1]->cargoAfter();
                $leg = new MultiLegRouteLeg(
                    $leg->tradeRoute(),
                    $previousCargo,
                    $leg->cargoAfter(),
                    $leg->cargoAfterPurchase(),
                    $leg->jumpPath(),
                );
            }

            $normalizedLegs[] = $leg;
        }

        $this->legs = $normalizedLegs;
        $this->currentCargo = clone $currentCargo;
    }

    /** @return list<MultiLegRouteLeg> */
    public function legs(): array
    {
        return $this->legs;
    }

    public function currentCargo(): CargoManifest
    {
        return clone $this->currentCargo;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function returnsToStart(): bool
    {
        return $this->closed;
    }

    public function totalJumpCount(): int
    {
        return $this->totalJumps();
    }

    public function totalStopCount(): int
    {
        return $this->totalTradeStops();
    }

    /** @return list<Station> */
    public function stopStations(): array
    {
        return array_map(
            static fn (MultiLegRouteLeg $leg): Station => $leg->destinationStation(),
            $this->legs,
        );
    }

    public function lastStop(): Station
    {
        return $this->legs[array_key_last($this->legs)]->destinationStation();
    }

    public function cargoCapacity(): int
    {
        return $this->currentCargo->capacity();
    }

    /** @return array<string, int> */
    public function cargoStateBeforeLeg(int $legIndex): array
    {
        return $this->cargoQuantities($this->cargoAtLeg($legIndex));
    }

    /** @return array<string, int> */
    public function cargoStateAfterPurchaseForLeg(int $legIndex): array
    {
        if (!isset($this->legs[$legIndex])) {
            throw new \OutOfBoundsException(sprintf('No route leg exists at index %d.', $legIndex));
        }

        return $this->cargoQuantities($this->legs[$legIndex]->cargoAfterPurchase());
    }

    public function totalJumps(): int
    {
        return array_sum(array_map(
            static fn (MultiLegRouteLeg $leg): int => $leg->jumpCount(),
            $this->legs,
        ));
    }

    public function totalTradeStops(): int
    {
        return count($this->legs);
    }

    public function netProfitCredits(): int
    {
        return array_sum(array_map(
            static fn (MultiLegRouteLeg $leg): int => $leg->netProfitCredits(),
            $this->legs,
        ));
    }

    public function estimatedDurationSeconds(): float
    {
        return array_sum(array_map(
            static fn (MultiLegRouteLeg $leg): float => $leg->tradeRoute()->estimatedDurationSeconds(),
            $this->legs,
        ));
    }

    public function creditsPerHour(): float
    {
        $duration = $this->estimatedDurationSeconds();

        return $duration > 0.0 ? $this->netProfitCredits() / ($duration / 3600.0) : 0.0;
    }

    private function cargoAtLeg(int $legIndex): CargoManifest
    {
        if (!isset($this->legs[$legIndex])) {
            throw new \OutOfBoundsException(sprintf('No route leg exists at index %d.', $legIndex));
        }

        return $this->legs[$legIndex]->cargoBefore();
    }

    /** @return array<string, int> */
    private function cargoQuantities(CargoManifest $cargo): array
    {
        $quantities = [];
        foreach ($cargo->positions() as $position) {
            $quantities[$position->commodityName()] = $position->quantity();
        }

        return $quantities;
    }
}
