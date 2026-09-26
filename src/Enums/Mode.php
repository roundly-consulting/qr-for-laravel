<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Enums;

use RoundlyConsulting\Enums\Helpers;

/**
 * Segment modes and their 4-bit indicators (ISO/IEC 18004:2015 §7.4.1, Table 2).
 */
enum Mode: int
{
    use Helpers;

    case Numeric = 0b0001;
    case Alphanumeric = 0b0010;
    case Byte = 0b0100;
    case Kanji = 0b1000;
    case Eci = 0b0111;

    /**
     * Length of the character count indicator (ISO §7.4.1, Table 3). ECI carries none.
     */
    public function charCountBits(int $version): int
    {
        $group = $version <= 9 ? 0 : ($version <= 26 ? 1 : 2);

        return match ($this) {
            self::Numeric => [10, 12, 14][$group],
            self::Alphanumeric => [9, 11, 13][$group],
            self::Byte => [8, 16, 16][$group],
            self::Kanji => [8, 10, 12][$group],
            self::Eci => 0,
        };
    }

    /**
     * Lower-case label used in encoding reports and fixtures.
     */
    public function key(): string
    {
        return strtolower($this->name);
    }
}
