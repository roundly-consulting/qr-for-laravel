<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Encoder;

/**
 * Mask penalty score (ISO/IEC 18004:2015 §7.8.3, Table 11), evaluated on '0'/'1' row
 * strings and their transposed columns:
 *
 *  - N1 = 3 for each run of five same-colour modules in a row or column, +1 per extra module;
 *  - N2 = 3 for each 2×2 block of one colour;
 *  - N3 = 40 for each 1:1:3:1:1 finder-like pattern with a light run of at least four
 *    widths on one side and at least one width on the other. The symbol is surrounded by
 *    light modules, so a light border as wide as the symbol extends the first and last run
 *    of every line;
 *  - N4 = 10 for each full 5 % the dark share deviates from 50 %.
 *
 * @internal
 */
final class MaskEvaluator
{
    public const int N1 = 3;

    public const int N2 = 3;

    public const int N3 = 40;

    public const int N4 = 10;

    /**
     * @param  array<int, string>  $rows
     */
    public static function penalty(array $rows): int
    {
        $size = count($rows);
        $penalty = 0;

        foreach ($rows as $row) {
            $penalty += self::linePenalty($row, $size);
        }

        foreach (self::columns($rows) as $column) {
            $penalty += self::linePenalty($column, $size);
        }

        return $penalty + self::blockPenalty($rows) + self::balancePenalty($rows);
    }

    /**
     * N1 + N3 for one row or column.
     */
    public static function linePenalty(string $line, int $size): int
    {
        preg_match_all('/0+|1+/', $line, $matches);
        $runs = array_map(strlen(...), $matches[0]);
        $penalty = 0;

        foreach ($runs as $run) {
            if ($run >= 5) {
                $penalty += self::N1 + $run - 5;
            }
        }

        // Light-first alternating run lengths with the light border folded into the ends.
        if ($line !== '' && $line[0] === '1') {
            array_unshift($runs, 0);
        }

        if (count($runs) % 2 === 0) {
            $runs[] = 0;
        }

        $runs[0] += $size;
        $runs[count($runs) - 1] += $size;

        for ($k = 6, $count = count($runs); $k < $count; $k += 2) {
            $n = $runs[$k - 1];

            if ($n === 0 || $runs[$k - 2] !== $n || $runs[$k - 4] !== $n || $runs[$k - 5] !== $n || $runs[$k - 3] !== 3 * $n) {
                continue;
            }

            if ($runs[$k] >= 4 * $n && $runs[$k - 6] >= $n) {
                $penalty += self::N3;
            }

            if ($runs[$k - 6] >= 4 * $n && $runs[$k] >= $n) {
                $penalty += self::N3;
            }
        }

        return $penalty;
    }

    /**
     * @param  array<int, string>  $rows
     */
    public static function blockPenalty(array $rows): int
    {
        $penalty = 0;

        for ($y = 0, $last = count($rows) - 1; $y < $last; $y++) {
            $top = $rows[$y];
            $bottom = $rows[$y + 1];

            // "\x00" where neighbours match: top vs bottom, top x vs x+1, bottom x vs x+1.
            $same = substr($top ^ $bottom, 0, -1) | (substr($top, 0, -1) ^ substr($top, 1)) | (substr($bottom, 0, -1) ^ substr($bottom, 1));
            $penalty += self::N2 * substr_count($same, "\x00");
        }

        return $penalty;
    }

    /**
     * @param  array<int, string>  $rows
     */
    public static function balancePenalty(array $rows): int
    {
        $total = count($rows) ** 2;
        $dark = substr_count(implode('', $rows), '1');
        $k = intdiv(abs($dark * 20 - $total * 10) + $total - 1, $total) - 1;

        return self::N4 * $k;
    }

    /**
     * @param  array<int, string>  $rows
     * @return list<string>
     */
    public static function columns(array $rows): array
    {
        $cells = array_map(str_split(...), $rows);
        $columns = [];

        for ($x = 0, $size = count($rows); $x < $size; $x++) {
            $columns[] = implode('', array_column($cells, $x));
        }

        return $columns;
    }
}
