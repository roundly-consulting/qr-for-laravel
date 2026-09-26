<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads\Payments\BySquare;

use Illuminate\Support\Str;

/**
 * Strips diacritics ("Príspevok na kávu" → "Prispevok na kavu") for banking apps that
 * cannot handle them. Applied to the note and the beneficiary fields only.
 *
 * @internal
 */
final class Deburr
{
    public static function apply(string $text): string
    {
        return Str::ascii($text);
    }
}
