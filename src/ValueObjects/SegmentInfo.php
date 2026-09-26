<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\ValueObjects;

use RoundlyConsulting\Qr\Enums\Mode;

final readonly class SegmentInfo
{
    public function __construct(
        public Mode $mode,
        public int $characterCount,
        public int $bitLength,
    ) {}
}
