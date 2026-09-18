<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Entity\Station;
use InvalidArgumentException;

final class EdsmCatalogMapper
{
    /** @return array{name:string, systemName:string, marketId:?string, stationType:string, landingClass:?string, hasMarket:bool, accessible:?bool} */
    public function station(array $record): array
    {
        $name = $this->string($record, 'name');
        $systemName = (string) ($record['systemName'] ?? $record['system']['name'] ?? '');
        if ($systemName === '') { throw new InvalidArgumentException('EDSM station has no system name.'); }
        $type = strtolower((string) ($record['type'] ?? ''));
        $stationType = match (true) {
            str_contains($type, 'outpost') => Station::TYPE_OUTPOST,
            str_contains($type, 'planet') || str_contains($type, 'surface') => Station::TYPE_PLANETARY_PORT,
            str_contains($type, 'fleet carrier') => Station::TYPE_FLEET_CARRIER,
            str_contains($type, 'megaship') => Station::TYPE_MEGASHIP,
            str_contains($type, 'coriolis'), str_contains($type, 'orbis'), str_contains($type, 'ocellus'), str_contains($type, 'starport') => Station::TYPE_ORBITAL,
            default => Station::TYPE_SPECIAL_MARKET,
        };
        $landing = match (strtoupper((string) ($record['maxLandingPadSize'] ?? ''))) {
            'S' => Station::LANDING_SMALL, 'M' => Station::LANDING_MEDIUM, 'L' => Station::LANDING_LARGE, default => null,
        };
        $marketId = isset($record['marketId']) && (string) $record['marketId'] !== '' ? (string) $record['marketId'] : null;
        return ['name' => $name, 'systemName' => $systemName, 'marketId' => $marketId, 'stationType' => $stationType,
            'landingClass' => $landing, 'hasMarket' => $marketId !== null || ($record['hasMarket'] ?? true) === true, 'accessible' => null];
    }

    /** @return array{name:string,x:?float,y:?float,z:?float,accessible:?bool} */
    public function system(array $record): array
    {
        $coords = $record['coords'] ?? [];
        return ['name' => $this->string($record, 'name'), 'x' => $this->float($coords, 'x'), 'y' => $this->float($coords, 'y'), 'z' => $this->float($coords, 'z'), 'accessible' => null];
    }

    private function string(array $record, string $key): string
    {
        $value = trim((string) ($record[$key] ?? ''));
        if ($value === '') { throw new InvalidArgumentException('EDSM record has no '.$key.'.'); }
        return $value;
    }
    private function float(array $record, string $key): ?float { return isset($record[$key]) && is_numeric($record[$key]) ? (float) $record[$key] : null; }
}
