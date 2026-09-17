<?php

namespace App\Domain\Cargo;

/**
 * The current load of one commodity, including its weighted purchase cost.
 *
 * A position never represents an empty load. CargoManifest removes a position
 * after its last unit has been sold.
 */
final readonly class CargoPosition
{
    public function __construct(
        private string $commodityName,
        private int $quantity,
        private int $totalPurchaseCost,
        private ?string $lastPurchaseStation = null,
    ) {
        if (trim($this->commodityName) === '') {
            throw new \InvalidArgumentException('commodityName must not be empty.');
        }

        if ($this->quantity < 1) {
            throw new \InvalidArgumentException('quantity must be positive.');
        }

        if ($this->totalPurchaseCost < 0) {
            throw new \InvalidArgumentException('totalPurchaseCost must not be negative.');
        }

        if ($this->lastPurchaseStation !== null && trim($this->lastPurchaseStation) === '') {
            throw new \InvalidArgumentException('lastPurchaseStation must not be empty when provided.');
        }
    }

    public function commodityName(): string
    {
        return $this->commodityName;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function totalPurchaseCost(): int
    {
        return $this->totalPurchaseCost;
    }

    public function averagePurchasePrice(): float
    {
        return $this->totalPurchaseCost / $this->quantity;
    }

    public function lastPurchaseStation(): ?string
    {
        return $this->lastPurchaseStation;
    }

    public function addPurchase(int $quantity, int $unitPrice, ?string $station = null): self
    {
        self::assertTransaction($quantity, $unitPrice, 'purchase');
        self::assertStation($station);

        $additionalCost = self::checkedProduct($quantity, $unitPrice);
        $newQuantity = self::checkedSum($this->quantity, $quantity);
        $newCost = self::checkedSum($this->totalPurchaseCost, $additionalCost);

        return new self($this->commodityName, $newQuantity, $newCost, $station ?? $this->lastPurchaseStation);
    }

    public function saleProfit(int $quantity, int $unitPrice): int
    {
        self::assertTransaction($quantity, $unitPrice, 'sale');
        if ($quantity > $this->quantity) {
            throw new \InvalidArgumentException('Cannot sell more units than the position contains.');
        }

        $revenue = self::checkedProduct($quantity, $unitPrice);

        return $revenue - $this->costBasisFor($quantity);
    }

    public function afterSale(int $quantity): ?self
    {
        if ($quantity < 1 || $quantity > $this->quantity) {
            throw new \InvalidArgumentException('Sale quantity must be within the current position.');
        }

        if ($quantity === $this->quantity) {
            return null;
        }

        return new self(
            $this->commodityName,
            $this->quantity - $quantity,
            $this->totalPurchaseCost - $this->costBasisFor($quantity),
            $this->lastPurchaseStation,
        );
    }

    private function costBasisFor(int $quantity): int
    {
        // Divide before multiplying so partial sales do not overflow merely
        // because the position contains many units.
        $wholeCostPerUnit = intdiv($this->totalPurchaseCost, $this->quantity);
        $remainder = $this->totalPurchaseCost % $this->quantity;

        return ($wholeCostPerUnit * $quantity) + intdiv($remainder * $quantity, $this->quantity);
    }

    private static function assertTransaction(int $quantity, int $unitPrice, string $kind): void
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException(sprintf('%s quantity must be positive.', ucfirst($kind)));
        }

        if ($unitPrice < 0) {
            throw new \InvalidArgumentException(sprintf('%s unit price must not be negative.', ucfirst($kind)));
        }
    }

    private static function assertStation(?string $station): void
    {
        if ($station !== null && trim($station) === '') {
            throw new \InvalidArgumentException('station must not be empty when provided.');
        }
    }

    private static function checkedProduct(int $left, int $right): int
    {
        if ($left !== 0 && $right > intdiv(PHP_INT_MAX, $left)) {
            throw new \OverflowException('Cargo transaction exceeds the supported credit range.');
        }

        return $left * $right;
    }

    private static function checkedSum(int $left, int $right): int
    {
        if ($right > PHP_INT_MAX - $left) {
            throw new \OverflowException('Cargo manifest exceeds the supported integer range.');
        }

        return $left + $right;
    }
}
