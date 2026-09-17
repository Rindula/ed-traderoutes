<?php

namespace App\Domain\Route;

use App\Entity\Station;

/**
 * The result of one executable trade leg from a source station to a destination station.
 */
final readonly class TradeRoute
{
    /** @var list<TradeOffer> */
    private array $tradeOffers;

    public function __construct(
        private Station $sourceStation,
        private Station $destinationStation,
        TradeOffer|array $tradeOffer,
        private float $systemDistance,
        private int $jumpCount,
        private float $estimatedDurationSeconds,
    ) {
        $offers = is_array($tradeOffer) ? array_values($tradeOffer) : [$tradeOffer];
        if ($offers === [] || array_filter($offers, static fn (mixed $offer): bool => !$offer instanceof TradeOffer) !== []) {
            throw new \InvalidArgumentException('A trade route requires at least one trade offer.');
        }
        $this->tradeOffers = $offers;
        if ($this->sourceStation->getSystem()->getId() === $this->destinationStation->getSystem()->getId()) {
            throw new \InvalidArgumentException('A single-leg trade must connect two different systems.');
        }

        if ($this->systemDistance <= 0.0 || !is_finite($this->systemDistance)) {
            throw new \InvalidArgumentException('systemDistance must be finite and positive.');
        }

        if ($this->jumpCount < 1) {
            throw new \InvalidArgumentException('jumpCount must be positive.');
        }

        if ($this->estimatedDurationSeconds <= 0.0 || !is_finite($this->estimatedDurationSeconds)) {
            throw new \InvalidArgumentException('estimatedDurationSeconds must be finite and positive.');
        }
    }

    public function sourceStation(): Station
    {
        return $this->sourceStation;
    }

    public function destinationStation(): Station
    {
        return $this->destinationStation;
    }

    /** @return list<TradeOffer> */
    public function tradeOffers(): array
    {
        return $this->tradeOffers;
    }

    public function tradeOffer(): TradeOffer
    {
        return $this->tradeOffers[0];
    }

    public function systemDistance(): float
    {
        return $this->systemDistance;
    }

    public function jumpCount(): int
    {
        return $this->jumpCount;
    }

    public function estimatedDurationSeconds(): float
    {
        return $this->estimatedDurationSeconds;
    }

    public function netProfitCredits(): int
    {
        return array_sum(array_map(static fn (TradeOffer $offer): int => $offer->totalProfitCredits(), $this->tradeOffers));
    }

    public function creditsPerHour(): float
    {
        return $this->netProfitCredits() / ($this->estimatedDurationSeconds / 3600.0);
    }
}
