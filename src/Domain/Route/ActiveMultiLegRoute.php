<?php

namespace App\Domain\Route;

use App\Entity\Station;

/**
 * Read model for the prominently displayed current leg and stop preview.
 */
readonly class ActiveMultiLegRoute
{
    public function __construct(private MultiLegRoute $route, private int $currentLegIndex)
    {
        if ($this->currentLegIndex < 0 || $this->currentLegIndex >= count($this->route->legs())) {
            throw new \OutOfBoundsException('The current leg index is outside the route.');
        }
    }

    public function currentLeg(): MultiLegRouteLeg
    {
        return $this->route->legs()[$this->currentLegIndex];
    }

    /** @return list<Station> */
    public function nextStops(int $limit = 3): array
    {
        if ($limit < 0) {
            throw new \InvalidArgumentException('The stop preview limit must not be negative.');
        }

        return array_map(
            static fn (MultiLegRouteLeg $leg): Station => $leg->destinationStation(),
            array_slice($this->route->legs(), $this->currentLegIndex + 1, $limit),
        );
    }

    public function route(): MultiLegRoute
    {
        return $this->route;
    }
}
