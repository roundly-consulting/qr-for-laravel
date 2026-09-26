<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Svg\Path;

/**
 * Path data for traced loops with rounded convex corners. The dark side of every loop is
 * on the right, so a right turn is always a convex corner: it becomes a quarter arc of the
 * given radius, while concave (left-turn) corners stay sharp.
 *
 * @internal
 */
final class RoundedOutline
{
    /**
     * @param  list<list<array{0: int, 1: int}>>  $loops
     */
    public static function render(array $loops, int $offset, float $radius): string
    {
        $path = '';

        foreach ($loops as $corners) {
            $path .= self::loop($corners, $offset, $radius);
        }

        return $path;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $corners
     */
    private static function loop(array $corners, int $offset, float $radius): string
    {
        $count = count($corners);
        $directions = [];

        for ($i = 0; $i < $count; $i++) {
            [$x, $y] = $corners[$i];
            [$nx, $ny] = $corners[($i + 1) % $count];
            $directions[$i] = [$nx <=> $x, $ny <=> $y, abs($nx - $x) + abs($ny - $y)];
        }

        $convex = [];

        for ($i = 0; $i < $count; $i++) {
            [$inX, $inY] = $directions[($i - 1 + $count) % $count];
            [$outX, $outY] = $directions[$i];
            // Right turn in y-down space: the outgoing direction is the incoming one rotated +90°.
            $convex[$i] = $outX === -$inY && $outY === $inX;
        }

        [$x, $y] = $corners[0];
        [$dx, $dy] = $directions[0];
        $shift = $convex[0] ? $radius : 0.0;
        $path = 'M'.PathFormatter::number($x + $offset + $dx * $shift).' '.PathFormatter::number($y + $offset + $dy * $shift);

        for ($i = 0; $i < $count; $i++) {
            [$dx, $dy, $length] = $directions[$i];
            $next = ($i + 1) % $count;
            $straight = $length - ($convex[$i] ? $radius : 0.0) - ($convex[$next] ? $radius : 0.0);

            if ($straight > 0) {
                $path .= PathFormatter::line($dx, $dy, $straight);
            }

            if ($convex[$next]) {
                [$ndx, $ndy] = $directions[$next];
                $path .= PathFormatter::arc($radius, ($dx + $ndx) * $radius, ($dy + $ndy) * $radius);
            }
        }

        return $path.'z';
    }
}
