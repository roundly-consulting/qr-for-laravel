<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Svg\Path;

/**
 * Path data for traced loops with sharp corners: an absolute move to each loop's first
 * corner, then relative `h`/`v` lines, closed with `z`.
 *
 * @internal
 */
final class SquarePath
{
    /**
     * @param  list<list<array{0: int, 1: int}>>  $loops
     */
    public static function render(array $loops, int $offset): string
    {
        $path = '';

        foreach ($loops as $corners) {
            [$x, $y] = $corners[0];
            $path .= 'M'.($x + $offset).' '.($y + $offset);

            // The last side back to the first corner is implied by `z`.
            for ($i = 1, $count = count($corners); $i < $count; $i++) {
                [$nx, $ny] = $corners[$i];
                $path .= $nx !== $x ? 'h'.($nx - $x) : 'v'.($ny - $y);
                [$x, $y] = [$nx, $ny];
            }

            $path .= 'z';
        }

        return $path;
    }
}
