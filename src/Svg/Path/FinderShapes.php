<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Svg\Path;

use RoundlyConsulting\Qr\Enums\FinderStyle;

/**
 * The three 7×7 finder patterns drawn as shapes of their own: a ring (7×7 outer edge,
 * 5×5 hole, even-odd) and a 3×3 centre, square or rounded.
 *
 * @internal
 */
final class FinderShapes
{
    public static function isFinder(int $x, int $y, int $size): bool
    {
        return ($x < 7 && $y < 7) || ($x >= $size - 7 && $y < 7) || ($x < 7 && $y >= $size - 7);
    }

    public static function render(int $size, int $offset, FinderStyle $style): string
    {
        $path = '';

        foreach ([[0, 0], [$size - 7, 0], [0, $size - 7]] as [$x, $y]) {
            $x += $offset;
            $y += $offset;

            $path .= $style === FinderStyle::Rounded
                ? self::roundedRect($x, $y, 7, 1.5).self::roundedRect($x + 1, $y + 1, 5, 1.0).self::roundedRect($x + 2, $y + 2, 3, 0.75)
                : 'M'.$x.' '.$y.'h7v7h-7z'.'M'.($x + 1).' '.($y + 1).'h5v5h-5z'.'M'.($x + 2).' '.($y + 2).'h3v3h-3z';
        }

        return $path;
    }

    private static function roundedRect(int $x, int $y, int $side, float $radius): string
    {
        $edge = $side - 2 * $radius;

        return 'M'.PathFormatter::number($x + $radius).' '.$y
            .PathFormatter::line(1, 0, $edge).PathFormatter::arc($radius, $radius, $radius)
            .PathFormatter::line(0, 1, $edge).PathFormatter::arc($radius, -$radius, $radius)
            .PathFormatter::line(-1, 0, $edge).PathFormatter::arc($radius, -$radius, -$radius)
            .PathFormatter::line(0, -1, $edge).PathFormatter::arc($radius, $radius, -$radius)
            .'z';
    }
}
