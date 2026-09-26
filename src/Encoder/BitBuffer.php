<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Encoder;

/**
 * An append-only bit sequence kept as a string of '0'/'1' characters (at most 23 648 bits
 * for a version 40 symbol), MSB first.
 *
 * @internal
 */
final class BitBuffer
{
    private string $bits = '';

    public function append(int $value, int $length): void
    {
        if ($length === 0) {
            return;
        }

        $this->bits .= str_pad(decbin($value & ((1 << $length) - 1)), $length, '0', STR_PAD_LEFT);
    }

    public function appendBits(string $bits): void
    {
        $this->bits .= $bits;
    }

    public function length(): int
    {
        return strlen($this->bits);
    }

    public function toString(): string
    {
        return $this->bits;
    }

    /**
     * The buffer as 8-bit codewords; the length must be a multiple of 8.
     *
     * @return list<int>
     */
    public function toCodewords(): array
    {
        $codewords = [];

        foreach (str_split($this->bits, 8) as $byte) {
            $codewords[] = (int) bindec($byte);
        }

        return $this->bits === '' ? [] : $codewords;
    }
}
