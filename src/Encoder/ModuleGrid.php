<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Encoder;

/**
 * The mutable working grid of an encode: module colours as '0'/'1' row strings plus a
 * parallel mask marking function modules (finders, timing, alignment, format/version
 * areas) that data placement and masking must skip.
 *
 * @internal
 */
final class ModuleGrid
{
    /**
     * @param  array<int, string>  $rows  '1' = dark
     * @param  array<int, string>  $function  '1' = function module
     */
    public function __construct(
        public readonly int $size,
        public array $rows,
        public array $function,
    ) {}

    public static function blank(int $size): self
    {
        $line = str_repeat('0', $size);

        return new self($size, array_fill(0, $size, $line), array_fill(0, $size, $line));
    }

    public function set(int $x, int $y, bool $dark): void
    {
        $this->rows[$y][$x] = $dark ? '1' : '0';
    }

    public function setFunction(int $x, int $y, bool $dark): void
    {
        $this->rows[$y][$x] = $dark ? '1' : '0';
        $this->function[$y][$x] = '1';
    }

    public function isDark(int $x, int $y): bool
    {
        return $this->rows[$y][$x] === '1';
    }

    public function isFunction(int $x, int $y): bool
    {
        return $this->function[$y][$x] === '1';
    }
}
