<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Enums\BySquare;

use RoundlyConsulting\Enums\Helpers;

/**
 * Standing-order execution months as the bit flags the by square month mask sums.
 */
enum Month: int
{
    use Helpers;

    case January = 1;
    case February = 2;
    case March = 4;
    case April = 8;
    case May = 16;
    case June = 32;
    case July = 64;
    case August = 128;
    case September = 256;
    case October = 512;
    case November = 1024;
    case December = 2048;

    public static function mask(self ...$months): int
    {
        $mask = 0;

        foreach ($months as $month) {
            $mask |= $month->value;
        }

        return $mask;
    }

    /**
     * @return list<self>
     */
    public static function fromMask(int $mask): array
    {
        $months = [];

        foreach (self::cases() as $case) {
            if (($mask & $case->value) !== 0) {
                $months[] = $case;
            }
        }

        return $months;
    }
}
