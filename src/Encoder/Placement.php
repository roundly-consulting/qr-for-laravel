<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Encoder;

/**
 * Codeword placement (ISO/IEC 18004:2015 §7.7.3): two-module-wide columns walked
 * alternately upwards and downwards from the bottom-right corner, skipping the vertical
 * timing column and every function module, most significant bit first. Modules left over
 * after the last codeword are remainder bits and stay light.
 *
 * @internal
 */
final class Placement
{
    /** @var array<int, list<array{0: int, 1: int}>> data module coordinates per version */
    private static array $coordinates = [];

    /**
     * Every non-function module in placement order.
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function coordinates(int $version): array
    {
        if (isset(self::$coordinates[$version])) {
            return self::$coordinates[$version];
        }

        $grid = FunctionPatterns::template($version);
        $size = $grid->size;
        $coordinates = [];

        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }

            $upward = (($right + 1) & 2) === 0;

            for ($vertical = 0; $vertical < $size; $vertical++) {
                $y = $upward ? $size - 1 - $vertical : $vertical;

                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;

                    if (! $grid->isFunction($x, $y)) {
                        $coordinates[] = [$x, $y];
                    }
                }
            }
        }

        return self::$coordinates[$version] = $coordinates;
    }

    /**
     * @param  list<int>  $codewords
     */
    public static function place(ModuleGrid $grid, int $version, array $codewords): void
    {
        $bits = '';

        foreach ($codewords as $codeword) {
            $bits .= str_pad(decbin($codeword), 8, '0', STR_PAD_LEFT);
        }

        $length = strlen($bits);

        foreach (self::coordinates($version) as $i => [$x, $y]) {
            if ($i >= $length) {
                break;
            }

            if ($bits[$i] === '1') {
                $grid->rows[$y][$x] = '1';
            }
        }
    }

    /**
     * Read the codewords back out of unmasked rows (the remainder bits are ignored).
     *
     * @param  array<int, string>  $rows
     * @return list<int>
     */
    public static function read(array $rows, int $version): array
    {
        $bits = '';

        foreach (self::coordinates($version) as [$x, $y]) {
            $bits .= $rows[$y][$x];
        }

        $codewords = [];

        foreach (str_split(substr($bits, 0, Capacity::totalCodewords($version) * 8), 8) as $byte) {
            $codewords[] = (int) bindec($byte);
        }

        return $codewords;
    }
}
