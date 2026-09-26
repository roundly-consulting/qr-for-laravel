<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Svg\Path;

/**
 * Locale-independent number formatting for path data: integers as integers, everything
 * else with at most three decimals and no trailing zeros (`%F` ignores the locale).
 *
 * @internal
 */
final class PathFormatter
{
    public static function number(float|int $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        $formatted = rtrim(rtrim(sprintf('%.3F', $value), '0'), '.');

        return $formatted === '-0' || $formatted === '' ? '0' : $formatted;
    }

    /**
     * A relative line along one axis: `h` for horizontal, `v` for vertical.
     */
    public static function line(int $dx, int $dy, float|int $length): string
    {
        return ($dx !== 0 ? 'h' : 'v').self::number(($dx + $dy) * $length);
    }

    /**
     * A relative quarter arc turning clockwise (SVG sweep flag 1 in y-down space).
     */
    public static function arc(float $radius, float $dx, float $dy): string
    {
        $r = self::number($radius);

        return 'a'.$r.' '.$r.' 0 0 1 '.self::number($dx).' '.self::number($dy);
    }
}
