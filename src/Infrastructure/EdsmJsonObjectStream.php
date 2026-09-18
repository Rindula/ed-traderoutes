<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Generator;
use JsonException;

final class EdsmJsonObjectStream
{
    /** @param iterable<string> $chunks @return Generator<int, array<string, mixed>> */
    public function decode(iterable $chunks): Generator
    {
        $buffer = '';
        $depth = 0;
        $start = null;
        $inString = false;
        $escaped = false;
        $seenArray = false;
        $closedArray = false;

        foreach ($chunks as $chunk) {
            for ($index = 0, $length = strlen($chunk); $index < $length; ++$index) {
                $character = $chunk[$index];
                if ($depth === 0 && $start === null) {
                    if ($closedArray && !ctype_space($character)) {
                        throw new JsonException('Unexpected data after EDSM JSON array.');
                    }
                    if (!$seenArray && ctype_space($character)) {
                        continue;
                    }
                    if (!$seenArray && $character !== '[') {
                        throw new JsonException('EDSM dump must be a JSON array of objects.');
                    }
                    if (!$seenArray) { $seenArray = true; continue; }
                    if ($character === ']') { $closedArray = true; continue; }
                    if (ctype_space($character) || $character === ',') { continue; }
                    if ($character !== '{') {
                        throw new JsonException('EDSM dump contains a non-object value.');
                    }
                    $start = strlen($buffer);
                    $depth = 1;
                    $buffer .= $character;
                    continue;
                }

                if ($inString) {
                    if ($escaped) { $escaped = false; }
                    elseif ($character === '\\') { $escaped = true; }
                    elseif ($character === '"') { $inString = false; }
                } elseif ($character === '"') {
                    $inString = true;
                } elseif ($character === '{') {
                    ++$depth;
                } elseif ($character === '}') {
                    --$depth;
                    if ($depth < 0) { throw new JsonException('Unexpected closing brace in EDSM JSON stream.'); }
                }
                $buffer .= $character;
                if ($depth === 0) {
                    try { $value = json_decode(substr($buffer, $start), true, 512, JSON_THROW_ON_ERROR); }
                    catch (JsonException $e) { throw new JsonException('Malformed EDSM object: '.$e->getMessage(), 0, $e); }
                    if (!is_array($value) || array_is_list($value)) { throw new JsonException('EDSM dump object must be a JSON object.'); }
                    yield $value;
                    $buffer = '';
                    $start = null;
                }
            }
        }
        if (!$seenArray || !$closedArray || $depth !== 0 || $inString || $start !== null) {
            throw new JsonException('Unexpected end of EDSM JSON stream.');
        }
    }
}
