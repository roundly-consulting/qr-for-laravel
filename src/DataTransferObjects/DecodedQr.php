<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\DataTransferObjects;

use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Testing\MatrixDecoder;
use RoundlyConsulting\Qr\ValueObjects\SegmentInfo;

/**
 * The content read back from a matrix by {@see MatrixDecoder}.
 */
final readonly class DecodedQr
{
    /**
     * @param  list<SegmentInfo>  $segments
     */
    public function __construct(
        public string $bytes,
        public int $version,
        public ErrorCorrection $errorCorrection,
        public int $mask,
        public array $segments,
        public ?int $eciDesignator,
    ) {}
}
