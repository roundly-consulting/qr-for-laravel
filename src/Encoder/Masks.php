<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Encoder;

/**
 * The eight data mask patterns (ISO/IEC 18004:2015 §7.8.2, Table 10), i = row, j = column.
 *
 * @internal
 */
final class Masks
{
    public static function isFlipped(int $mask, int $x, int $y): bool
    {
        return match ($mask) {
            0 => ($y + $x) % 2 === 0,
            1 => $y % 2 === 0,
            2 => $x % 3 === 0,
            3 => ($y + $x) % 3 === 0,
            4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
            5 => ($y * $x) % 2 + ($y * $x) % 3 === 0,
            6 => (($y * $x) % 2 + ($y * $x) % 3) % 2 === 0,
            default => (($y + $x) % 2 + ($y * $x) % 3) % 2 === 0,
        };
    }

    /**
     * XOR the mask over every non-function module. Applying the same mask twice restores
     * the grid.
     */
    public static function apply(ModuleGrid $grid, int $mask): void
    {
        foreach ($grid->rows as $y => $row) {
            $flip = '';

            for ($x = 0; $x < $grid->size; $x++) {
                $flip .= $grid->function[$y][$x] === '0' && self::isFlipped($mask, $x, $y) ? "\x01" : "\x00";
            }

            // '0' ^ "\x01" = '1' and '1' ^ "\x01" = '0'; "\x00" leaves the module as is.
            $grid->rows[$y] = $row ^ $flip;
        }
    }
}
