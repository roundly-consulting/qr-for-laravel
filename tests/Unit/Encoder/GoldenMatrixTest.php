<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Encoder\Encoder;
use RoundlyConsulting\Qr\Testing\MatrixDecoder;
use RoundlyConsulting\Qr\Tests\Support\GoldenFixtures;

/**
 * Golden matrices from an independent ISO/IEC 18004 encoder; static, regenerated only when
 * a case is added. Covers every level at versions 1–40 (character-count boundaries 9/10 and
 * 26/27, exact-capacity payloads), all eight forced masks and automatic mask selection.
 */
it('pins the golden fixture count', function (): void {
    expect(GoldenFixtures::all())->toHaveCount(64);
});

it('reproduces every golden matrix bit for bit', function (string $name): void {
    $case = GoldenFixtures::all()[$name];

    $matrix = (new Encoder)->encodeSegments($case['segments'], $case['options']);

    expect($matrix->version())->toBe($case['version'])
        ->and($matrix->mask())->toBe($case['mask'])
        ->and($matrix->rows())->toBe($case['rows']);

    expect(MatrixDecoder::decode($matrix)->bytes)->toBe($case['data']);
})->with(fn (): array => array_keys(GoldenFixtures::all()));
