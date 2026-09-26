<?php

declare(strict_types=1);

/*
 * Encoder micro-benchmark (not run in CI): php bin/bench.php [iterations]
 */

use RoundlyConsulting\Qr\DataTransferObjects\EncodeOptions;
use RoundlyConsulting\Qr\Encoder\Encoder;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;

require __DIR__.'/../vendor/autoload.php';

$iterations = max(1, (int) ($argv[1] ?? 10));
$encoder = new Encoder;
$cases = [
    'v1-M text' => ['HELLO WORLD', ErrorCorrection::Medium],
    'otpauth URI' => ['otpauth://totp/Acme:user%40acme.io?secret=JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP&issuer=Acme&algorithm=SHA1&digits=6&period=30', ErrorCorrection::Medium],
    'v40-H' => [str_repeat('A', 1800), ErrorCorrection::High],
];

foreach ($cases as $label => [$data, $ecc]) {
    $encoder->encode($data, new EncodeOptions($ecc));
    $start = hrtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $matrix = $encoder->encode($data, new EncodeOptions($ecc));
    }

    printf("%-12s v%-2d %8.2f ms/encode\n", $label, $matrix->version(), (hrtime(true) - $start) / 1e6 / $iterations);
}
