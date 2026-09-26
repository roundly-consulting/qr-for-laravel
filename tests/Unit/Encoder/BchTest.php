<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Encoder\Bch;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;

it('encodes the format information of ISO Annex C', function (ErrorCorrection $ecc, int $mask, string $bits): void {
    expect(str_pad(decbin(Bch::formatBits($ecc, $mask)), 15, '0', STR_PAD_LEFT))->toBe($bits);
})->with([
    [ErrorCorrection::Low, 0, '111011111000100'],
    [ErrorCorrection::Low, 7, '110100101110110'],
    [ErrorCorrection::Medium, 0, '101010000010010'],
    [ErrorCorrection::Medium, 7, '100101010100000'],
    [ErrorCorrection::Quartile, 0, '011010101011111'],
    [ErrorCorrection::Quartile, 7, '010101111101101'],
    [ErrorCorrection::High, 0, '001011010001001'],
    [ErrorCorrection::High, 7, '000100000111011'],
]);

it('encodes the version information of ISO Annex D', function (): void {
    expect(Bch::versionBits(7))->toBe(0x07C94)
        ->and(Bch::versionBits(40))->toBe(0x28C69);
});

it('keeps every pair of format code words at least seven bits apart', function (): void {
    $words = [];

    foreach (ErrorCorrection::cases() as $ecc) {
        foreach (range(0, 7) as $mask) {
            $words[] = Bch::formatBits($ecc, $mask);
        }
    }

    foreach ($words as $i => $a) {
        foreach (array_slice($words, $i + 1) as $b) {
            expect(Bch::hammingDistance($a, $b))->toBeGreaterThanOrEqual(7);
        }
    }
});
