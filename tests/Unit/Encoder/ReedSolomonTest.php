<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Encoder\GaloisField;
use RoundlyConsulting\Qr\Encoder\ReedSolomon;

it('builds the generator polynomials of ISO Annex A', function (int $degree, array $exponents): void {
    expect(array_map(GaloisField::log(...), ReedSolomon::generator($degree)))->toBe($exponents);
})->with([
    [7, [87, 229, 146, 149, 238, 102, 21]],
    [10, [251, 67, 46, 61, 118, 70, 64, 94, 32, 45]],
]);

it('computes the error-correction codewords of the ISO examples', function (array $data, int $degree, array $ecc): void {
    expect(ReedSolomon::remainder($data, $degree))->toBe($ecc)
        ->and(ReedSolomon::isValid([...$data, ...$ecc], $degree))->toBeTrue();
})->with([
    '01234567 1-M' => [
        [0x10, 0x20, 0x0C, 0x56, 0x61, 0x80, 0xEC, 0x11, 0xEC, 0x11, 0xEC, 0x11, 0xEC, 0x11, 0xEC, 0x11],
        10,
        [0xA5, 0x24, 0xD4, 0xC1, 0xED, 0x36, 0xC7, 0x87, 0x2C, 0x55],
    ],
    'HELLO WORLD 1-Q' => [
        [32, 91, 11, 120, 209, 114, 220, 77, 67, 64, 236, 17, 236],
        13,
        [168, 72, 22, 82, 217, 54, 156, 0, 46, 15, 180, 122, 16],
    ],
]);

it('detects a corrupted block', function (): void {
    $data = [32, 91, 11, 120, 209, 114, 220, 77, 67, 64, 236, 17, 236];
    $block = [...$data, ...ReedSolomon::remainder($data, 13)];
    $block[3] ^= 0x01;

    expect(ReedSolomon::isValid($block, 13))->toBeFalse();
});
