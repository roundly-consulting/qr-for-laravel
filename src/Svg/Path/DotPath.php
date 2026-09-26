<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Svg\Path;

/**
 * One circle per dark module, drawn as two half arcs.
 *
 * @internal
 */
final class DotPath
{
    /**
     * @param  array<int, string>  $rows
     * @param  callable(int, int): bool  $exclude
     */
    public static function render(array $rows, int $offset, float $radius, callable $exclude): string
    {
        $r = PathFormatter::number($radius);
        $diameter = PathFormatter::number(2 * $radius);
        $path = '';

        foreach ($rows as $y => $row) {
            for ($x = 0, $size = strlen($row); $x < $size; $x++) {
                if ($row[$x] !== '1' || $exclude($x, $y)) {
                    continue;
                }

                $path .= 'M'.PathFormatter::number($x + $offset + 0.5 - $radius).' '.PathFormatter::number($y + $offset + 0.5)
                    .'a'.$r.' '.$r.' 0 1 0 '.$diameter.' 0'
                    .'a'.$r.' '.$r.' 0 1 0 -'.$diameter.' 0z';
            }
        }

        return $path;
    }
}
