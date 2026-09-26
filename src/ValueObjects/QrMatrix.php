<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\ValueObjects;

use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;

/**
 * An immutable, finished QR symbol: `size` × `size` modules, (0, 0) top-left, without the
 * quiet zone.
 */
final class QrMatrix
{
    /**
     * @param  list<string>  $rows  '1' = dark module
     */
    public function __construct(
        private readonly array $rows,
        private readonly EncodingInfo $info,
    ) {}

    public function size(): int
    {
        return count($this->rows);
    }

    public function version(): int
    {
        return $this->info->version;
    }

    public function errorCorrection(): ErrorCorrection
    {
        return $this->info->errorCorrection;
    }

    public function mask(): int
    {
        return $this->info->mask;
    }

    /**
     * @throws InvalidOptionException for a module outside the symbol
     */
    public function isDark(int $x, int $y): bool
    {
        $size = $this->size();

        if ($x < 0 || $y < 0 || $x >= $size || $y >= $size) {
            throw InvalidOptionException::outOfBounds($x, $y, $size);
        }

        return $this->rows[$y][$x] === '1';
    }

    /**
     * Row $y as '0'/'1' characters.
     */
    public function row(int $y): string
    {
        if ($y < 0 || $y >= $this->size()) {
            throw InvalidOptionException::outOfBounds(0, $y, $this->size());
        }

        return $this->rows[$y];
    }

    /**
     * @return list<string>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    public function darkModuleCount(): int
    {
        return substr_count(implode('', $this->rows), '1');
    }

    public function info(): EncodingInfo
    {
        return $this->info;
    }

    public function equals(self $other): bool
    {
        return $this->rows === $other->rows;
    }

    /**
     * A plain-text rendering for debugging and tests — not an image format.
     */
    public function toText(string $dark = '██', string $light = '  ', int $quietZone = 4): string
    {
        $width = $this->size() + 2 * $quietZone;
        $blank = str_repeat($light, $width);
        $lines = array_fill(0, $quietZone, $blank);

        foreach ($this->rows as $row) {
            $lines[] = str_repeat($light, $quietZone)
                .strtr($row, ['1' => $dark, '0' => $light])
                .str_repeat($light, $quietZone);
        }

        return implode("\n", [...$lines, ...array_fill(0, $quietZone, $blank)])."\n";
    }
}
