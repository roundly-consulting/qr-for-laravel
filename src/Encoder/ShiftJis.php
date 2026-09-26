<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Encoder;

/**
 * Kanji mode compaction (ISO/IEC 18004:2015 §7.4.6): a double-byte Shift JIS value in
 * 0x8140–0x9FFC or 0xE040–0xEBBF is reduced by 0x8140 or 0xC140, then packed as
 * (high byte × 0xC0 + low byte) into 13 bits.
 *
 * @internal
 */
final class ShiftJis
{
    /**
     * The 13-bit kanji value of one UTF-8 character, or null when it has no double-byte
     * Shift JIS form in the kanji ranges.
     */
    public static function kanjiValue(string $character): ?int
    {
        $sjis = mb_convert_encoding($character, 'SJIS', 'UTF-8');

        if (strlen($sjis) !== 2 || mb_convert_encoding($sjis, 'UTF-8', 'SJIS') !== $character) {
            return null;
        }

        $code = (ord($sjis[0]) << 8) | ord($sjis[1]);

        if ($code < 0x8140 || $code > 0xEBBF || ($code > 0x9FFC && $code < 0xE040)) {
            return null;
        }

        $code -= $code <= 0x9FFC ? 0x8140 : 0xC140;

        return ($code >> 8) * 0xC0 + ($code & 0xFF);
    }

    public static function isKanji(string $character): bool
    {
        return self::kanjiValue($character) !== null;
    }

    /**
     * The UTF-8 character behind a 13-bit kanji value.
     */
    public static function fromKanjiValue(int $value): string
    {
        $code = intdiv($value, 0xC0) << 8 | $value % 0xC0;
        $code += $code + 0x8140 <= 0x9FFC ? 0x8140 : 0xC140;

        return mb_convert_encoding(chr($code >> 8).chr($code & 0xFF), 'UTF-8', 'SJIS');
    }
}
