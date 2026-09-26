<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Compression\Lzma;

/**
 * LZMA stream parameters (LZMA specification: "The lzma properties"): literal context
 * bits, literal position bits, position bits and dictionary size. PAY by square uses
 * lc=3, lp=0, pb=2 and a 128 KiB dictionary.
 *
 * @internal
 */
final readonly class LzmaProperties
{
    public function __construct(
        public int $lc = 3,
        public int $lp = 0,
        public int $pb = 2,
        public int $dictionarySize = 1 << 17,
    ) {}

    /**
     * The properties byte: (pb × 5 + lp) × 9 + lc (0x5D for the defaults).
     */
    public function byte(): int
    {
        return ($this->pb * 5 + $this->lp) * 9 + $this->lc;
    }

    /**
     * The 13-byte `.lzma` header: properties byte, little-endian dictionary size and a
     * 64-bit little-endian uncompressed size (all ones = unknown, stream ends with a marker).
     */
    public function header(?int $size = null): string
    {
        return chr($this->byte()).pack('V', $this->dictionarySize)
            .($size === null ? str_repeat("\xFF", 8) : pack('P', $size));
    }
}
