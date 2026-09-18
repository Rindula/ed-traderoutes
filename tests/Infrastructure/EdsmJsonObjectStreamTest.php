<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure;

use App\Infrastructure\EdsmJsonObjectStream;
use PHPUnit\Framework\TestCase;

final class EdsmJsonObjectStreamTest extends TestCase
{
    public function testItYieldsObjectsFromAJsonArrayAcrossChunks(): void
    {
        $stream = new EdsmJsonObjectStream();

        $objects = iterator_to_array($stream->decode([
            '[{"name":"Sol","coords":{"x":0,"y":0,"z":0}},',
            '{"name":"Alpha \\"Centauri\\"","coords":{"x":1,"y":2,"z":3}}]'
        ]));

        self::assertSame('Sol', $objects[0]['name']);
        self::assertSame('Alpha "Centauri"', $objects[1]['name']);
        self::assertSame(3, $objects[1]['coords']['z']);
    }

    public function testItRejectsMalformedObjects(): void
    {
        $this->expectException(\JsonException::class);
        iterator_to_array((new EdsmJsonObjectStream())->decode(['[{"name":}]']));
    }
}
