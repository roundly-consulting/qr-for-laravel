<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\DataTransferObjects\EncodeOptions;
use RoundlyConsulting\Qr\Encoder\Encoder;
use RoundlyConsulting\Qr\Encoder\Segment;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\Segmentation;
use RoundlyConsulting\Qr\Testing\MatrixDecoder;

/**
 * Seeded pseudo-random strings × levels × strategies: every symbol must decode back to its
 * input. Deterministic (mt_srand), so a failure reproduces.
 */
it('decodes every generated symbol back to its input', function (): void {
    mt_srand(18004);
    $alphabets = ['0123456789', Segment::ALPHANUMERIC_CHARSET, 'abcdefghijklmnopqrstuvwxyz0123456789 -_./', 'ABCdef123 ', 'áčďéíľňóŕšťúýž0123'];
    $encoder = new Encoder;

    for ($case = 0; $case < 300; $case++) {
        $alphabet = mb_str_split($alphabets[mt_rand(0, count($alphabets) - 1)]);
        $length = mt_rand(0, $case < 280 ? 120 : 500);
        $data = '';

        for ($i = 0; $i < $length; $i++) {
            $data .= $alphabet[mt_rand(0, count($alphabet) - 1)];
        }

        $options = new EncodeOptions(
            errorCorrection: ErrorCorrection::cases()[mt_rand(0, 3)],
            segmentation: Segmentation::cases()[mt_rand(0, 2)],
            boostErrorCorrection: mt_rand(0, 1) === 1,
        );

        expect(MatrixDecoder::decode($encoder->encode($data, $options))->bytes)->toBe($data);
    }
});
