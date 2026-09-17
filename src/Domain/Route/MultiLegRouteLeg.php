<?php

namespace App\Domain\Route;

use App\Domain\Cargo\CargoManifest;
use App\Entity\System;

/**
 * One executable trade leg and the cargo state on both sides of its purchase.
 */
final readonly class MultiLegRouteLeg
{
    public function __construct(
        private TradeRoute $tradeRoute,
        CargoManifest $cargoBefore,
        CargoManifest $cargoAfter,
        ?CargoManifest $cargoAfterPurchase = null,
        array $jumpPath = [],
    ) {
        if ($cargoBefore->capacity() !== $cargoAfter->capacity()) {
            throw new \InvalidArgumentException('Cargo snapshots must use the same capacity.');
        }

        $this->cargoBefore = clone $cargoBefore;
        $this->cargoAfter = clone $cargoAfter;
        $this->cargoAfterPurchase = clone ($cargoAfterPurchase ?? $cargoAfter);
        $this->jumpPath = $this->normalizeJumpPath($jumpPath);
    }

    private CargoManifest $cargoBefore;
    private CargoManifest $cargoAfter;
    private CargoManifest $cargoAfterPurchase;

    /** @var list<System> */
    private array $jumpPath;

    public function tradeRoute(): TradeRoute
    {
        return $this->tradeRoute;
    }

    public function sourceStation(): \App\Entity\Station
    {
        return $this->tradeRoute->sourceStation();
    }

    public function destinationStation(): \App\Entity\Station
    {
        return $this->tradeRoute->destinationStation();
    }

    public function cargoBefore(): CargoManifest
    {
        return clone $this->cargoBefore;
    }

    public function cargoAfter(): CargoManifest
    {
        return clone $this->cargoAfter;
    }

    public function cargoAfterPurchase(): CargoManifest
    {
        return clone $this->cargoAfterPurchase;
    }

    /** @return list<System> */
    public function jumpPath(): array
    {
        return $this->jumpPath;
    }

    public function jumpCount(): int
    {
        return $this->tradeRoute->jumpCount();
    }

    public function netProfitCredits(): int
    {
        return $this->tradeRoute->netProfitCredits();
    }

    /** @param list<System> $jumpPath @return list<System> */
    private function normalizeJumpPath(array $jumpPath): array
    {
        if ($jumpPath === []) {
            return [];
        }

        foreach ($jumpPath as $system) {
            if (!$system instanceof System) {
                throw new \InvalidArgumentException('A jump path may only contain System instances.');
            }
        }

        if (count($jumpPath) - 1 !== $this->tradeRoute->jumpCount()) {
            throw new \InvalidArgumentException('Jump path length must match the route jump count.');
        }

        if ($jumpPath[0]->getId() !== $this->sourceStation()->getSystem()->getId()
            || $jumpPath[array_key_last($jumpPath)]->getId() !== $this->destinationStation()->getSystem()->getId()) {
            throw new \InvalidArgumentException('Jump path endpoints must match the trade leg.');
        }

        return array_values($jumpPath);
    }
}
