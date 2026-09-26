<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Encoder\GaloisField;

it('builds exp and log tables over 0x11D', function (): void {
    expect(GaloisField::exp(0))->toBe(1)
        ->and(GaloisField::exp(1))->toBe(2)
        ->and(GaloisField::exp(8))->toBe(0x1D)
        ->and(GaloisField::exp(255))->toBe(1)
        ->and(GaloisField::log(1))->toBe(0)
        ->and(GaloisField::log(0x1D))->toBe(8);

    foreach (range(1, 255) as $value) {
        expect(GaloisField::exp(GaloisField::log($value)))->toBe($value);
    }
});

it('multiplies field elements', function (): void {
    expect(GaloisField::multiply(0, 7))->toBe(0)
        ->and(GaloisField::multiply(7, 0))->toBe(0)
        ->and(GaloisField::multiply(1, 200))->toBe(200)
        ->and(GaloisField::multiply(2, 0x80))->toBe(0x1D)
        ->and(GaloisField::multiply(0x53, 0xCA))->toBe(GaloisField::multiply(0xCA, 0x53));
});
