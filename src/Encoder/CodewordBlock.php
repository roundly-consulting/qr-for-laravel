<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Encoder;

/**
 * One error-correction block: its data codewords and their Reed-Solomon codewords.
 *
 * @internal
 */
final readonly class CodewordBlock
{
    /**
     * @param  list<int>  $data
     * @param  list<int>  $ecc
     */
    public function __construct(
        public array $data,
        public array $ecc,
    ) {}

    /**
     * @return list<int>
     */
    public function codewords(): array
    {
        return [...$this->data, ...$this->ecc];
    }
}
