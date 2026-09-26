<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Tests\Support;

/**
 * Rasterises SVG path data (M, h, v, circular a, z — arcs sampled as polylines) with the
 * even-odd rule, sampling module centres. Proves a rendered path reproduces its matrix.
 */
final class PathRasterizer
{
    /**
     * @return list<list<array{0: float, 1: float}>>
     */
    public static function polygons(string $d): array
    {
        preg_match_all('/([MhvaAz])([^MhvaAz]*)/', $d, $commands, PREG_SET_ORDER);
        $polygons = [];
        $current = [];
        $x = 0.0;
        $y = 0.0;

        foreach ($commands as [, $command, $arguments]) {
            $numbers = array_map('floatval', preg_split('/[\s,]+/', trim($arguments), flags: PREG_SPLIT_NO_EMPTY) ?: []);

            switch ($command) {
                case 'M':
                    if ($current !== []) {
                        $polygons[] = $current;
                    }
                    [$x, $y] = $numbers;
                    $current = [[$x, $y]];
                    break;
                case 'h':
                    $x += $numbers[0];
                    $current[] = [$x, $y];
                    break;
                case 'v':
                    $y += $numbers[0];
                    $current[] = [$x, $y];
                    break;
                case 'a':
                    foreach (self::arcPoints($x, $y, $numbers[0], (int) $numbers[3], (int) $numbers[4], $x + $numbers[5], $y + $numbers[6]) as $point) {
                        $current[] = $point;
                    }
                    $x += $numbers[5];
                    $y += $numbers[6];
                    break;
                case 'z':
                    $polygons[] = $current;
                    $current = [];
                    break;
            }
        }

        return $polygons;
    }

    /**
     * Points along a circular SVG arc (endpoint parameterisation, SVG 1.1 appendix F.6),
     * ending at the arc's end point.
     *
     * @return list<array{0: float, 1: float}>
     */
    private static function arcPoints(float $x1, float $y1, float $r, int $largeArc, int $sweep, float $x2, float $y2): array
    {
        $hx = ($x1 - $x2) / 2;
        $hy = ($y1 - $y2) / 2;
        $numerator = max(0.0, $r * $r - $hx * $hx - $hy * $hy);
        $denominator = $hx * $hx + $hy * $hy;
        $coefficient = ($largeArc === $sweep ? -1 : 1) * sqrt($denominator > 0 ? $numerator / $denominator : 0.0);
        $cx = $coefficient * $hy + ($x1 + $x2) / 2;
        $cy = -$coefficient * $hx + ($y1 + $y2) / 2;
        $start = atan2($y1 - $cy, $x1 - $cx);
        $delta = atan2($y2 - $cy, $x2 - $cx) - $start;

        if ($sweep === 1 && $delta <= 0) {
            $delta += 2 * M_PI;
        } elseif ($sweep === 0 && $delta >= 0) {
            $delta -= 2 * M_PI;
        }

        $points = [];

        for ($k = 1; $k <= 16; $k++) {
            $points[] = $k === 16 ? [$x2, $y2] : [$cx + $r * cos($start + $delta * $k / 16), $cy + $r * sin($start + $delta * $k / 16)];
        }

        return $points;
    }

    /**
     * The rows the path paints, sampled at module centres with an even-odd scanline
     * (quiet zone removed).
     *
     * @return list<string>
     */
    public static function rows(string $d, int $size, int $margin): array
    {
        $crossings = array_fill(0, $size, []);

        foreach (self::polygons($d) as $polygon) {
            $count = count($polygon);

            for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
                [$xi, $yi] = $polygon[$i];
                [$xj, $yj] = $polygon[$j];

                if ($yi === $yj) {
                    continue;
                }

                $low = max(0, (int) ceil(min($yi, $yj) - $margin - 0.5));
                $high = min($size - 1, (int) floor(max($yi, $yj) - $margin - 0.5));

                for ($row = $low; $row <= $high; $row++) {
                    $py = $row + $margin + 0.5;

                    if (($yi > $py) !== ($yj > $py)) {
                        $crossings[$row][] = ($xj - $xi) * ($py - $yi) / ($yj - $yi) + $xi;
                    }
                }
            }
        }

        $rows = [];

        foreach ($crossings as $row => $xs) {
            sort($xs);
            $line = '';

            for ($x = 0; $x < $size; $x++) {
                $px = $x + $margin + 0.5;
                $before = 0;

                foreach ($xs as $crossing) {
                    if ($crossing < $px) {
                        $before++;
                    }
                }

                $line .= $before % 2 === 1 ? '1' : '0';
            }

            $rows[] = $line;
        }

        return $rows;
    }

    /**
     * Every `d` attribute of a rendered SVG, joined.
     */
    public static function pathData(string $svg): string
    {
        preg_match_all('/ d="([^"]*)"/', $svg, $matches);

        return implode('', $matches[1]);
    }
}
