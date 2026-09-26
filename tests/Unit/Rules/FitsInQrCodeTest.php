<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Exceptions\DataTooLongException;
use RoundlyConsulting\Qr\Facades\Qr;
use RoundlyConsulting\Qr\Rules\FitsInQrCode;

it('defaults to level M, any version and optimal segmentation', function (): void {
    expect(Validator::make(['code' => str_repeat('A', 3391)], ['code' => [new FitsInQrCode]])->passes())->toBeTrue()
        ->and(Validator::make(['code' => str_repeat('A', 3392)], ['code' => [new FitsInQrCode]])->passes())->toBeFalse();
});

it('passes content that fits and fails content that does not', function (mixed $value, FitsInQrCode $rule, bool $passes): void {
    $validator = Validator::make(['code' => $value], ['code' => [$rule]]);

    expect($validator->passes())->toBe($passes);

    if (! $passes) {
        expect($validator->errors()->first('code'))->toBe('The code is too long to fit in a QR code.');
    }
})->with([
    'short text' => ['hello', new FitsInQrCode, true],
    'number' => [12345, new FitsInQrCode, true],
    'EPC limit' => [str_repeat('a', 331), new FitsInQrCode(ErrorCorrection::Medium, 13), true],
    'over EPC limit' => [str_repeat('a', 332), new FitsInQrCode(ErrorCorrection::Medium, 13), false],
    'too long for any symbol' => [str_repeat('a', 3000), new FitsInQrCode, false],
    'not a string' => [['array'], new FitsInQrCode, false],
]);

it('defaults to the configured level, version window, ECI policy and kanji switch', function (): void {
    // 3391 alphanumerics fit version 40 at M, not at H: with the configured level H the rule
    // must fail what Qr::text() would then refuse to encode.
    config(['qr.error_correction' => 'H']);
    $text = str_repeat('A', 3391);

    expect(Validator::make(['code' => $text], ['code' => [new FitsInQrCode]])->passes())->toBeFalse()
        ->and(fn () => Qr::text($text)->matrix())->toThrow(DataTooLongException::class)
        ->and(Validator::make(['code' => $text], ['code' => [new FitsInQrCode(ErrorCorrection::Medium)]])->passes())->toBeTrue();

    config(['qr.error_correction' => 'M', 'qr.versions.max' => 10]);

    expect(Validator::make(['code' => str_repeat('a', 214)], ['code' => [new FitsInQrCode]])->passes())->toBeFalse()
        ->and(Validator::make(['code' => str_repeat('a', 214)], ['code' => [new FitsInQrCode(maxVersion: 40)]])->passes())->toBeTrue();

    // 213 bytes fit version 10-M, but not with the 12-bit ECI header eci=always adds.
    config(['qr.versions.max' => 10, 'qr.eci' => 'always']);
    $utf = str_repeat('a', 213);

    expect(fn () => Qr::text($utf)->matrix())->toThrow(DataTooLongException::class)
        ->and(Validator::make(['code' => $utf], ['code' => [new FitsInQrCode]])->passes())->toBeFalse();
});
