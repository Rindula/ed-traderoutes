<?php

namespace App\Domain\Route;

/**
 * Bounds and closure policy for a stateful sequence of trade legs.
 */
final readonly class MultiLegRouteRequest
{
    public function __construct(
        private RouteCalculationRequest $legRequest,
        private int $maxTotalJumps,
        private int $maxTradeStops,
        private bool $returnToStart = false,
    ) {
        if ($this->maxTotalJumps < 1) {
            throw new \InvalidArgumentException('maxTotalJumps must be positive.');
        }

        if ($this->maxTradeStops < 1) {
            throw new \InvalidArgumentException('maxTradeStops must be positive.');
        }
    }

    public function legRequest(): RouteCalculationRequest
    {
        return $this->legRequest;
    }

    public function maxTotalJumps(): int
    {
        return $this->maxTotalJumps;
    }

    public function maxTradeStops(): int
    {
        return $this->maxTradeStops;
    }

    public function returnsToStart(): bool
    {
        return $this->returnToStart;
    }

    public function isClosed(): bool
    {
        return $this->returnToStart;
    }
}
