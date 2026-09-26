<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Compression\Lzma;

/**
 * Binary range encoder, the mirror of {@see RangeDecoder} (LZMA specification: "The range
 * encoder"): `low` carries a 33rd bit, and pending 0xFF bytes wait in a cache until a carry
 * is resolved. The first output byte is always zero; flushing shifts out five bytes.
 *
 * @internal
 */
final class RangeEncoder
{
    private const int TOP = 1 << 24;

    private int $low = 0;

    private int $range = 0xFFFFFFFF;

    private int $cache = 0;

    private int $cacheSize = 1;

    private string $output = '';

    /**
     * @param  array<int, int>  $probabilities
     */
    public function bit(array &$probabilities, int $index, int $bit): void
    {
        $probability = $probabilities[$index];
        $bound = ($this->range >> 11) * $probability;

        if ($bit === 0) {
            $this->range = $bound;
            $probabilities[$index] = $probability + ((2048 - $probability) >> 5);
        } else {
            $this->low += $bound;
            $this->range -= $bound;
            $probabilities[$index] = $probability - ($probability >> 5);
        }

        while ($this->range < self::TOP) {
            $this->range = ($this->range << 8) & 0xFFFFFFFF;
            $this->shiftLow();
        }
    }

    /**
     * @param  array<int, int>  $probabilities
     */
    public function bitTree(array &$probabilities, int $offset, int $bits, int $symbol): void
    {
        $m = 1;

        for ($i = $bits - 1; $i >= 0; $i--) {
            $bit = ($symbol >> $i) & 1;
            $this->bit($probabilities, $offset + $m, $bit);
            $m = ($m << 1) | $bit;
        }
    }

    /**
     * @param  array<int, int>  $probabilities
     */
    public function reverseBitTree(array &$probabilities, int $offset, int $bits, int $symbol): void
    {
        $m = 1;

        for ($i = 0; $i < $bits; $i++) {
            $bit = $symbol & 1;
            $symbol >>= 1;
            $this->bit($probabilities, $offset + $m, $bit);
            $m = ($m << 1) | $bit;
        }
    }

    public function directBits(int $value, int $bits): void
    {
        for ($i = $bits - 1; $i >= 0; $i--) {
            $this->range >>= 1;

            if ((($value >> $i) & 1) === 1) {
                $this->low += $this->range;
            }

            while ($this->range < self::TOP) {
                $this->range = ($this->range << 8) & 0xFFFFFFFF;
                $this->shiftLow();
            }
        }
    }

    public function finish(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $this->shiftLow();
        }

        return $this->output;
    }

    private function shiftLow(): void
    {
        if (($this->low & 0xFFFFFFFF) < 0xFF000000 || $this->low > 0xFFFFFFFF) {
            $carry = $this->low > 0xFFFFFFFF ? 1 : 0;
            $byte = $this->cache;

            do {
                $this->output .= chr(($byte + $carry) & 0xFF);
                $byte = 0xFF;
            } while (--$this->cacheSize !== 0);

            $this->cache = ($this->low >> 24) & 0xFF;
        }

        $this->cacheSize++;
        $this->low = ($this->low & 0x00FFFFFF) << 8;
    }
}
