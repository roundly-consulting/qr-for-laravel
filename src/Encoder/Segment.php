<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Encoder;

use RoundlyConsulting\Qr\Enums\Mode;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;
use RoundlyConsulting\Qr\Exceptions\UnencodableCharacterException;

/**
 * One mode segment of a QR bit stream (ISO/IEC 18004:2015 §7.4): its mode, character
 * count and encoded data bits (a '0'/'1' string, without mode indicator or count).
 */
final readonly class Segment
{
    /** The 45-character alphanumeric set in code order (ISO §7.4.4, Table 5). */
    public const string ALPHANUMERIC_CHARSET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

    public const int ECI_UTF8 = 26;

    private function __construct(
        public Mode $mode,
        public int $characterCount,
        public string $bits,
        public ?int $eciDesignator = null,
    ) {}

    /**
     * Digits 0-9, packed three to 10 bits (ISO §7.4.3).
     *
     * @throws UnencodableCharacterException
     */
    public static function numeric(string $digits): self
    {
        $invalid = strspn($digits, '0123456789');

        if ($invalid !== strlen($digits)) {
            throw UnencodableCharacterException::at(Mode::Numeric, $invalid);
        }

        $bits = '';

        foreach (str_split($digits, 3) as $group) {
            $bits .= str_pad(decbin((int) $group), strlen($group) * 3 + 1, '0', STR_PAD_LEFT);
        }

        return new self(Mode::Numeric, strlen($digits), $bits);
    }

    /**
     * Characters of the 45-character set, packed two to 11 bits (ISO §7.4.4).
     *
     * @throws UnencodableCharacterException
     */
    public static function alphanumeric(string $text): self
    {
        $valid = strspn($text, self::ALPHANUMERIC_CHARSET);

        if ($valid !== strlen($text)) {
            throw UnencodableCharacterException::at(Mode::Alphanumeric, $valid);
        }

        $bits = '';

        foreach (str_split($text, 2) as $pair) {
            $first = strpos(self::ALPHANUMERIC_CHARSET, $pair[0]);

            $bits .= strlen($pair) === 2
                ? str_pad(decbin($first * 45 + (int) strpos(self::ALPHANUMERIC_CHARSET, $pair[1])), 11, '0', STR_PAD_LEFT)
                : str_pad(decbin((int) $first), 6, '0', STR_PAD_LEFT);
        }

        return new self(Mode::Alphanumeric, strlen($text), $bits);
    }

    /**
     * Arbitrary bytes, eight bits each (ISO §7.4.5).
     */
    public static function bytes(string $bytes): self
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        return new self(Mode::Byte, strlen($bytes), $bits);
    }

    /**
     * Kanji characters (UTF-8 input), 13 bits each after Shift JIS compaction (ISO §7.4.6).
     *
     * @throws UnencodableCharacterException
     */
    public static function kanji(string $text): self
    {
        $bits = '';
        $count = 0;

        foreach (mb_str_split($text, 1, 'UTF-8') as $position => $character) {
            $value = ShiftJis::kanjiValue($character) ?? throw UnencodableCharacterException::at(Mode::Kanji, $position);
            $bits .= str_pad(decbin($value), 13, '0', STR_PAD_LEFT);
            $count++;
        }

        return new self(Mode::Kanji, $count, $bits);
    }

    /**
     * An Extended Channel Interpretation designator (ISO §7.4.2.2): 0-127 in one byte,
     * up to 16 383 in two, up to 999 999 in three.
     *
     * @throws InvalidOptionException
     */
    public static function eci(int $designator): self
    {
        $bits = match (true) {
            $designator < 0 => null,
            $designator < 1 << 7 => str_pad(decbin($designator), 8, '0', STR_PAD_LEFT),
            $designator < 1 << 14 => '10'.str_pad(decbin($designator), 14, '0', STR_PAD_LEFT),
            $designator < 1_000_000 => '110'.str_pad(decbin($designator), 21, '0', STR_PAD_LEFT),
            default => null,
        };

        return $bits === null
            ? throw InvalidOptionException::eci($designator)
            : new self(Mode::Eci, 0, $bits, $designator);
    }

    public static function isNumeric(string $text): bool
    {
        return strspn($text, '0123456789') === strlen($text);
    }

    public static function isAlphanumeric(string $text): bool
    {
        return strspn($text, self::ALPHANUMERIC_CHARSET) === strlen($text);
    }

    /**
     * Mode indicator + character count + data bits at the given version, or null when the
     * character count overflows the count indicator.
     */
    public function bitLength(int $version): ?int
    {
        $countBits = $this->mode->charCountBits($version);

        if ($this->mode !== Mode::Eci && $this->characterCount >= 1 << $countBits) {
            return null;
        }

        return 4 + $countBits + strlen($this->bits);
    }

    /**
     * @param  list<self>  $segments
     */
    public static function totalBits(array $segments, int $version): ?int
    {
        $total = 0;

        foreach ($segments as $segment) {
            $length = $segment->bitLength($version);

            if ($length === null) {
                return null;
            }

            $total += $length;
        }

        return $total;
    }
}
