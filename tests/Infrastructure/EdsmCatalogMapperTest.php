<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure;

use App\Entity\Station;
use App\Infrastructure\EdsmCatalogMapper;
use PHPUnit\Framework\TestCase;

final class EdsmCatalogMapperTest extends TestCase
{
    public function testMapsConservativeStationFieldsToCatalogValues(): void
    {
        $mapped = (new EdsmCatalogMapper())->station([
            'name' => 'Jameson Memorial',
            'systemName' => 'Shinrarta Dezhra',
            'marketId' => 128666762,
            'type' => 'Coriolis Starport',
            'maxLandingPadSize' => 'L',
        ]);

        self::assertSame('Jameson Memorial', $mapped['name']);
        self::assertSame('Shinrarta Dezhra', $mapped['systemName']);
        self::assertSame('128666762', $mapped['marketId']);
        self::assertSame(Station::TYPE_ORBITAL, $mapped['stationType']);
        self::assertSame(Station::LANDING_LARGE, $mapped['landingClass']);
        self::assertTrue($mapped['hasMarket']);
    }

    public function testUnknownStationTypeDoesNotGuessAStationConstant(): void
    {
        $mapped = (new EdsmCatalogMapper())->station(['name' => 'Unknown', 'systemName' => 'Sol', 'type' => 'Unknown Thing']);

        self::assertSame(Station::TYPE_SPECIAL_MARKET, $mapped['stationType']);
        self::assertNull($mapped['landingClass']);
    }
}
