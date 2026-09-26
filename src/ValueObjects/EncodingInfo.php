<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\ValueObjects;

use RoundlyConsulting\Qr\Enums\ErrorCorrection;

/**
 * How a symbol was built: version, level (and whether it was boosted), mask, segments and
 * bit budget. `maskPenalties` holds the eight ISO penalty scores when the mask was chosen
 * automatically, and is empty for a forced mask.
 */
final readonly class EncodingInfo
{
    /**
     * @param  list<SegmentInfo>  $segments
     * @param  list<int>  $maskPenalties
     */
    public function __construct(
        public int $version,
        public ErrorCorrection $errorCorrection,
        public int $mask,
        public array $segments,
        public int $dataBits,
        public int $capacityBits,
        public ?int $eciDesignator,
        public array $maskPenalties,
        public bool $errorCorrectionBoosted,
    ) {}
}
