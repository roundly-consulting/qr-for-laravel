<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Compression\Lzma;

use RoundlyConsulting\Qr\Exceptions\CorruptLzmaStreamException;

/**
 * Binary range decoder (LZMA specification: "The range decoder"). The first stream byte
 * must be zero; the next four initialise the code; the range is renormalised by shifting in
 * a byte whenever it drops below 2^24.
 *
 * @internal
 */
final class RangeDecoder
{
    private const int TOP = 1 << 24;

    private int $range = 0xFFFFFFFF;

    private int $code = 0;

    private int $position = 0;

    private readonly int $length;

    /**
     * @throws CorruptLzmaStreamException
     */
    public function __construct(private readonly string $input)
    {
        $this->length = strlen($input);

        if ($this->length < 5 || $input[0] !== "\x00") {
            throw CorruptLzmaStreamException::because('the range coder header is invalid');
        }

        $this->position = 1;

        for ($i = 0; $i < 4; $i++) {
            $this->code = ($this->code << 8) | ord($input[$this->position++]);
        }

        if ($this->code === $this->range) {
            throw CorruptLzmaStreamException::because('the range coder header is invalid');
        }
    }

    /**
     * @param  array<int, int>  $probabilities
     */
    public function bit(array &$probabilities, int $index): int
    {
        $probability = $probabilities[$index];
        $bound = ($this->range >> 11) * $probability;

        if ($this->code < $bound) {
            $this->range = $bound;
            $probabilities[$index] = $probability + ((2048 - $probability) >> 5);
            $bit = 0;
        } else {
            $this->code -= $bound;
            $this->range -= $bound;
            $probabilities[$index] = $probability - ($probability >> 5);
            $bit = 1;
        }

        $this->normalize();

        return $bit;
    }

    /**
     * @param  array<int, int>  $probabilities
     */
    public function bitTree(array &$probabilities, int $offset, int $bits): int
    {
        $m = 1;

        for ($i = 0; $i < $bits; $i++) {
            $m = ($m << 1) + $this->bit($probabilities, $offset + $m);
        }

        return $m - (1 << $bits);
    }

    /**
     * @param  array<int, int>  $probabilities
     */
    public function reverseBitTree(array &$probabilities, int $offset, int $bits): int
    {
        $m = 1;
        $symbol = 0;

        for ($i = 0; $i < $bits; $i++) {
            $bit = $this->bit($probabilities, $offset + $m);
            $m = ($m << 1) + $bit;
            $symbol |= $bit << $i;
        }

        return $symbol;
    }

    public function directBits(int $bits): int
    {
        $result = 0;

        for ($i = 0; $i < $bits; $i++) {
            $this->range >>= 1;
            $bit = 0;

            if ($this->code >= $this->range) {
                $this->code -= $this->range;
                $bit = 1;
            }

            $result = ($result << 1) | $bit;
            $this->normalize();
        }

        return $result;
    }

    /**
     * A correctly finished stream leaves the code at zero.
     */
    public function isFinishedOk(): bool
    {
        return $this->code === 0;
    }

    private function normalize(): void
    {
        if ($this->range >= self::TOP) {
            return;
        }

        if ($this->position >= $this->length) {
            throw CorruptLzmaStreamException::because('the input ends before the data');
        }

        $this->range = ($this->range << 8) & 0xFFFFFFFF;
        $this->code = (($this->code << 8) | ord($this->input[$this->position++])) & 0xFFFFFFFF;
    }
}
