<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\DataTransferObjects\EncodeOptions;
use RoundlyConsulting\Qr\Encoder\Encoder;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;

it('encodes a version 40-H symbol with automatic masking within budget', function (): void {
    $start = hrtime(true);
    $matrix = (new Encoder)->encode(str_repeat('A', 1800), new EncodeOptions(ErrorCorrection::High));
    $elapsed = (hrtime(true) - $start) / 1e9;

    expect($matrix->version())->toBe(40)
        ->and($elapsed)->toBeLessThan(2.0);
});
