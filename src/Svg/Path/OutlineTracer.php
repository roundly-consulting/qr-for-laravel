<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Svg\Path;

/**
 * Traces the outlines of dark regions on the integer module lattice.
 *
 * Every dark module contributes a directed boundary edge on each side that borders a
 * light module, oriented so the dark side is on the right: outer boundaries come out
 * clockwise and holes counter-clockwise (y-down), which suits `fill-rule="evenodd"`. Where
 * two dark modules touch only diagonally a vertex has two outgoing edges; the walk takes
 * the right turn, keeping the regions as separate loops. Collinear edges are merged, so a
 * loop is a list of corner points.
 *
 * @internal
 */
final class OutlineTracer
{
    /** Unit steps for east, south, west, north (y grows downwards). */
    private const array STEPS = [[1, 0], [0, 1], [-1, 0], [0, -1]];

    /**
     * @param  array<int, string>  $rows  '1' = dark
     * @param  (callable(int, int): bool)|null  $exclude  modules to leave out (e.g. finders drawn separately)
     * @return list<list<array{0: int, 1: int}>> corner points of each closed loop, in module coordinates
     */
    public static function loops(array $rows, ?callable $exclude = null): array
    {
        $size = count($rows);
        $width = $size + 1;
        $dark = static fn (int $x, int $y): bool => $x >= 0 && $y >= 0 && $x < $size && $y < $size
            && $rows[$y][$x] === '1' && ($exclude === null || ! $exclude($x, $y));

        /** outgoing edge directions per vertex key (y · width + x) */
        $edges = [];

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if (! $dark($x, $y)) {
                    continue;
                }

                if (! $dark($x, $y - 1)) {
                    $edges[$y * $width + $x][] = 0;
                }

                if (! $dark($x + 1, $y)) {
                    $edges[$y * $width + $x + 1][] = 1;
                }

                if (! $dark($x, $y + 1)) {
                    $edges[($y + 1) * $width + $x + 1][] = 2;
                }

                if (! $dark($x - 1, $y)) {
                    $edges[($y + 1) * $width + $x][] = 3;
                }
            }
        }

        ksort($edges);
        $loops = [];

        foreach (array_keys($edges) as $start) {
            while (isset($edges[$start]) && $edges[$start] !== []) {
                $loops[] = self::walk($edges, $start, $width);
            }
        }

        return $loops;
    }

    /**
     * @param  array<int, list<int>>  $edges  consumed as the walk goes
     * @return list<array{0: int, 1: int}>
     */
    private static function walk(array &$edges, int $start, int $width): array
    {
        $vertex = $start;
        $direction = self::take($edges, $vertex, null);
        $corners = [[$start % $width, intdiv($start, $width)]];

        while (true) {
            [$dx, $dy] = self::STEPS[$direction];
            $x = $vertex % $width + $dx;
            $y = intdiv($vertex, $width) + $dy;
            $vertex = $y * $width + $x;

            if ($vertex === $start) {
                break;
            }

            $next = self::take($edges, $vertex, $direction);

            if ($next !== $direction) {
                $corners[] = [$x, $y];
            }

            $direction = $next;
        }

        // Loops are walked from their top-left-most vertex, which is always a corner.
        return $corners;
    }

    /**
     * Remove and return an outgoing edge of the vertex: the right turn when there is a
     * choice, otherwise the only one.
     *
     * @param  array<int, list<int>>  $edges
     */
    private static function take(array &$edges, int $vertex, ?int $incoming): int
    {
        $options = $edges[$vertex];
        $pick = 0;

        if ($incoming !== null && count($options) > 1) {
            $right = ($incoming + 1) % 4;
            $found = array_search($right, $options, true);
            $pick = $found === false ? 0 : $found;
        }

        $direction = $options[$pick];
        array_splice($options, $pick, 1);

        if ($options === []) {
            unset($edges[$vertex]);
        } else {
            $edges[$vertex] = $options;
        }

        return $direction;
    }
}
