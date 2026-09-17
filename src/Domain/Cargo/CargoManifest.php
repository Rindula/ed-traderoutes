<?php

namespace App\Domain\Cargo;

/**
 * Stateful cargo aggregate for one planned route.
 *
 * The manifest deliberately does not track a visited-station set: repeat
 * stations are valid route steps. Every new manifest starts empty and only
 * its own buy/sell transitions affect the cargo state.
 */
final class CargoManifest
{
    /** @var array<string, CargoPosition> */
    private array $positions = [];

    public function __construct(private readonly int $capacity)
    {
        if ($this->capacity < 0) {
            throw new \InvalidArgumentException('capacity must not be negative.');
        }
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function usedCapacity(): int
    {
        return array_sum(array_map(
            static fn (CargoPosition $position): int => $position->quantity(),
            $this->positions,
        ));
    }

    public function remainingCapacity(): int
    {
        return $this->capacity - $this->usedCapacity();
    }

    public function isEmpty(): bool
    {
        return $this->positions === [];
    }

    public function hasCommodity(string $commodityName): bool
    {
        return isset($this->positions[$this->keyFor($commodityName)]);
    }

    public function positionFor(string $commodityName): ?CargoPosition
    {
        return $this->positions[$this->keyFor($commodityName)] ?? null;
    }

    /**
     * @return list<CargoPosition>
     */
    public function positions(): array
    {
        return array_values($this->positions);
    }

    /**
     * Buy a partial or full load. Multiple commodities and repeated station
     * visits are supported; no route-level uniqueness is imposed here.
     */
    public function buy(string $commodityName, int $quantity, int $unitPrice, ?string $station = null): void
    {
        $commodityName = $this->normalizeCommodityName($commodityName);
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Purchase quantity must be positive.');
        }

        if ($unitPrice < 0) {
            throw new \InvalidArgumentException('Purchase unit price must not be negative.');
        }

        if ($quantity > $this->remainingCapacity()) {
            throw new \InvalidArgumentException('Cargo capacity would be exceeded.');
        }

        $key = $this->keyFor($commodityName);
        $this->positions[$key] = isset($this->positions[$key])
            ? $this->positions[$key]->addPurchase($quantity, $unitPrice, $station)
            : new CargoPosition($commodityName, $quantity, $this->checkedProduct($quantity, $unitPrice), $station);

        $this->assertInvariant();
    }

    /**
     * Sell a partial or complete position and return the realized gross profit.
     */
    public function sell(string $commodityName, int $quantity, int $unitPrice): int
    {
        $commodityName = $this->normalizeCommodityName($commodityName);
        $key = $this->keyFor($commodityName);
        $position = $this->positions[$key] ?? null;
        if ($position === null) {
            throw new \InvalidArgumentException(sprintf('No cargo position exists for "%s".', $commodityName));
        }

        $profit = $position->saleProfit($quantity, $unitPrice);
        $remaining = $position->afterSale($quantity);
        if ($remaining === null) {
            unset($this->positions[$key]);
        } else {
            $this->positions[$key] = $remaining;
        }

        $this->assertInvariant();

        return $profit;
    }

    private function assertInvariant(): void
    {
        if ($this->usedCapacity() > $this->capacity) {
            throw new \LogicException('Cargo manifest invariant violated: cargo exceeds capacity.');
        }
    }

    private function normalizeCommodityName(string $commodityName): string
    {
        $commodityName = trim($commodityName);
        if ($commodityName === '') {
            throw new \InvalidArgumentException('commodityName must not be empty.');
        }

        return $commodityName;
    }

    private function keyFor(string $commodityName): string
    {
        return strtolower(trim($commodityName));
    }

    private function checkedProduct(int $left, int $right): int
    {
        if ($left !== 0 && $right > intdiv(PHP_INT_MAX, $left)) {
            throw new \OverflowException('Cargo transaction exceeds the supported credit range.');
        }

        return $left * $right;
    }
}
