<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads\Payments\BySquare;

use RoundlyConsulting\Qr\Enums\BySquare\BySquareVersion;

/**
 * A decoded PAY by square frame: the specification version and the serialised data.
 *
 * @internal
 */
final readonly class BySquareFrame
{
    public function __construct(
        public BySquareVersion $version,
        public string $serialized,
    ) {}
}
