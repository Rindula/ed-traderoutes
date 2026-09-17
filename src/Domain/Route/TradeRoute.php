<?php

namespace App\Domain\Route;

use App\Entity\Station;

/**
 * The result of one executable trade leg from a source station to a destination station.
 */
final readonly class TradeRoute
{
    public function __construct(
        private Station $sourceStation,
        private Station $destinationStation,
        private TradeOffer $tradeOffer,
        private float $systemDistance,
        private int $jumpCount,
        private float $estimatedDurationSeconds,
    ) {
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

    public function tradeOffer(): TradeOffer
    {
        return $this->tradeOffer;
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
        return $this->tradeOffer->totalProfitCredits();
    }

    public function creditsPerHour(): float
    {
        return $this->netProfitCredits() / ($this->estimatedDurationSeconds / 3600.0);
    }
}
