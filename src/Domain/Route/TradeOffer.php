<?php

namespace App\Domain\Route;

use App\Entity\MarketObservation;

/**
 * A reliable, executable buy-at-source/sell-at-destination opportunity.
 */
final readonly class TradeOffer
{
    private function __construct(
        private string $commodityName,
        private int $buyPrice,
        private int $sellPrice,
        private int $quantity,
        private MarketObservation $sourceObservation,
        private MarketObservation $destinationObservation,
    ) {
    }

    public static function fromObservations(
        MarketObservation $sourceObservation,
        MarketObservation $destinationObservation,
        int $cargoCapacity,
    ): ?self {
        if ($sourceObservation->getCommodityName() !== $destinationObservation->getCommodityName()) {
            return null;
        }

        $buyPrice = $sourceObservation->getBuyPrice();
        $sellPrice = $destinationObservation->getSellPrice();
        $supply = $sourceObservation->getStock();
        $demand = $destinationObservation->getDemand();

        if ($buyPrice === null || $buyPrice <= 0 || $sellPrice === null || $sellPrice <= 0) {
            return null;
        }

        if ($supply === null || $supply <= 0 || $demand === null || $demand <= 0 || $cargoCapacity <= 0) {
            return null;
        }

        $profitPerUnit = $sellPrice - $buyPrice;
        if ($profitPerUnit <= 0) {
            return null;
        }

        $quantity = min($cargoCapacity, $supply, $demand);

        return $quantity > 0 ? new self(
            $sourceObservation->getCommodityName(),
            $buyPrice,
            $sellPrice,
            $quantity,
            $sourceObservation,
            $destinationObservation,
        ) : null;
    }

    public function commodityName(): string
    {
        return $this->commodityName;
    }

    public function buyPrice(): int
    {
        return $this->buyPrice;
    }

    public function sellPrice(): int
    {
        return $this->sellPrice;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function profitPerUnit(): int
    {
        return $this->sellPrice - $this->buyPrice;
    }

    public function totalProfitCredits(): int
    {
        return $this->profitPerUnit() * $this->quantity;
    }

    public function sourceObservation(): MarketObservation
    {
        return $this->sourceObservation;
    }

    public function destinationObservation(): MarketObservation
    {
        return $this->destinationObservation;
    }
}
