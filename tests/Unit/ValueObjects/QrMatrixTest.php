<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\DataTransferObjects\EncodeOptions;
use RoundlyConsulting\Qr\Encoder\Encoder;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;

it('exposes the symbol', function (): void {
    $matrix = (new Encoder)->encode('HELLO WORLD', new EncodeOptions(ErrorCorrection::Quartile, boostErrorCorrection: false));

    expect($matrix->size())->toBe(21)
        ->and($matrix->version())->toBe(1)
        ->and($matrix->errorCorrection())->toBe(ErrorCorrection::Quartile)
        ->and($matrix->mask())->toBeBetween(0, 7)
        ->and($matrix->isDark(0, 0))->toBeTrue()
        ->and($matrix->isDark(7, 0))->toBeFalse()
        ->and($matrix->row(0))->toStartWith('1111111')
        ->and($matrix->rows())->toHaveCount(21)
        ->and($matrix->darkModuleCount())->toBe(substr_count(implode('', $matrix->rows()), '1'))
        ->and($matrix->info()->capacityBits)->toBe(13 * 8);
});

it('guards module access', function (int $x, int $y): void {
    (new Encoder)->encode('x')->isDark($x, $y);
})->throws(InvalidOptionException::class)->with([[-1, 0], [0, -1], [21, 0], [0, 21]]);

it('guards row access', function (int $y): void {
    (new Encoder)->encode('x')->row($y);
})->throws(InvalidOptionException::class)->with([-1, 21]);

it('renders a debug text form with a quiet zone', function (): void {
    $text = (new Encoder)->encode('x')->toText('#', '.', 1);
    $lines = explode("\n", rtrim($text));

    expect($lines)->toHaveCount(23)
        ->and($lines[0])->toBe(str_repeat('.', 23))
        ->and($lines[1])->toStartWith('.#######.');
});
