<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads\Payments\BySquare;

use Illuminate\Support\Str;
use RoundlyConsulting\Qr\Support\TextNormalizer;

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

    /**
     * The field as it is written when deburring is on: cleaned, deburred and trimmed, so a
     * word that has no ASCII form ("李明 商店") leaves no stray space behind.
     */
    public static function field(string $text): string
    {
        return trim(self::apply(TextNormalizer::clean($text)));
    }
}
