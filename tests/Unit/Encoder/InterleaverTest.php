<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Encoder\Capacity;
use RoundlyConsulting\Qr\Encoder\Interleaver;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;

it('splits into short blocks first and interleaves reversibly', function (int $version, ErrorCorrection $ecc): void {
    $data = array_map(static fn (int $i): int => $i % 256, range(0, Capacity::dataCodewords($version, $ecc) - 1));

    $blocks = Interleaver::split($data, $version, $ecc);
    $lengths = array_map(static fn ($block): int => count($block->data), $blocks);

    expect($blocks)->toHaveCount(Capacity::numBlocks($version, $ecc))
        ->and($lengths)->toBe(array_values($lengths === [] ? [] : (function (array $l): array {
            sort($l);

            return $l;
        })($lengths)))
        ->and(max($lengths) - min($lengths))->toBeLessThanOrEqual(1);

    $interleaved = Interleaver::interleave($data, $version, $ecc);

    expect($interleaved)->toHaveCount(Capacity::totalCodewords($version));

    $restored = Interleaver::deinterleave($interleaved, $version, $ecc);

    expect(array_merge(...array_map(static fn ($block): array => $block->data, $restored)))->toBe($data)
        ->and($restored)->toEqual($blocks);
})->with([
    [1, ErrorCorrection::Low],
    [5, ErrorCorrection::Quartile],
    [13, ErrorCorrection::Medium],
    [40, ErrorCorrection::High],
]);
