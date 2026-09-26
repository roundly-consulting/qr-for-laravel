<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Encoder;

/**
 * Per-version function-pattern templates (ISO/IEC 18004:2015 §6.3): finder patterns with
 * separators, timing patterns, alignment patterns, the dark module and the reserved format
 * and version information areas. Templates depend only on the version, so each is built
 * once per process and cloned per encode.
 *
 * @internal
 */
final class FunctionPatterns
{
    /** @var array<int, ModuleGrid> */
    private static array $templates = [];

    public static function template(int $version): ModuleGrid
    {
        if (! isset(self::$templates[$version])) {
            self::$templates[$version] = self::build($version);
        }

        return clone self::$templates[$version];
    }

    /**
     * Alignment pattern centre coordinates (ISO §6.3.6, Annex E), ascending.
     *
     * @return list<int>
     */
    public static function alignmentPositions(int $version): array
    {
        if ($version === 1) {
            return [];
        }

        $count = intdiv($version, 7) + 2;
        $step = intdiv($version * 8 + $count * 3 + 5, $count * 4 - 4) * 2;
        $positions = [];

        for ($i = 0, $position = Capacity::size($version) - 7; $i < $count - 1; $i++, $position -= $step) {
            $positions[] = $position;
        }

        $positions[] = 6;

        return array_reverse($positions);
    }

    /**
     * Module coordinates of format bits 0..14 (bit 0 = least significant): the copy around
     * the top-left finder, then the copy split between the top-right and bottom-left finders.
     *
     * @return list<list<array{0: int, 1: int}>>
     */
    public static function formatCoordinates(int $size): array
    {
        $primary = [];
        $secondary = [];

        for ($i = 0; $i < 15; $i++) {
            $primary[] = match (true) {
                $i < 6 => [8, $i],
                $i === 6 => [8, 7],
                $i === 7 => [8, 8],
                $i === 8 => [7, 8],
                default => [14 - $i, 8],
            };

            $secondary[] = $i < 8 ? [$size - 1 - $i, 8] : [8, $size - 15 + $i];
        }

        return [$primary, $secondary];
    }

    public static function drawFormat(ModuleGrid $grid, int $bits): void
    {
        foreach (self::formatCoordinates($grid->size) as $copy) {
            foreach ($copy as $i => [$x, $y]) {
                $grid->setFunction($x, $y, (($bits >> $i) & 1) === 1);
            }
        }

        // The dark module (ISO §6.3.8) sits next to the second copy and is always dark.
        $grid->setFunction(8, $grid->size - 8, true);
    }

    /**
     * Version information bit i sits at (size − 11 + i mod 3, ⌊i / 3⌋) and transposed.
     */
    public static function drawVersion(ModuleGrid $grid, int $version): void
    {
        if ($version < 7) {
            return;
        }

        $bits = Bch::versionBits($version);

        for ($i = 0; $i < 18; $i++) {
            $dark = (($bits >> $i) & 1) === 1;
            $a = $grid->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $grid->setFunction($a, $b, $dark);
            $grid->setFunction($b, $a, $dark);
        }
    }

    private static function build(int $version): ModuleGrid
    {
        $size = Capacity::size($version);
        $grid = ModuleGrid::blank($size);

        for ($i = 0; $i < $size; $i++) {
            $grid->setFunction(6, $i, $i % 2 === 0);
            $grid->setFunction($i, 6, $i % 2 === 0);
        }

        foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$cx, $cy]) {
            self::drawFinder($grid, $cx, $cy);
        }

        $positions = self::alignmentPositions($version);
        $last = count($positions) - 1;

        foreach ($positions as $i => $cx) {
            foreach ($positions as $j => $cy) {
                // Skip the three centres that coincide with finder patterns.
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $last) || ($i === $last && $j === 0)) {
                    continue;
                }

                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $grid->setFunction($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }

        // Reserve the format areas (drawn per mask later) and draw the version information.
        self::drawFormat($grid, 0);
        self::drawVersion($grid, $version);

        return $grid;
    }

    /**
     * A 7×7 finder pattern plus its light separator, clipped to the symbol.
     */
    private static function drawFinder(ModuleGrid $grid, int $cx, int $cy): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $cx + $dx;
                $y = $cy + $dy;

                if ($x < 0 || $y < 0 || $x >= $grid->size || $y >= $grid->size) {
                    continue;
                }

                $distance = max(abs($dx), abs($dy));
                $grid->setFunction($x, $y, $distance !== 2 && $distance !== 4);
            }
        }
    }
}
