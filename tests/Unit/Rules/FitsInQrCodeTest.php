<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
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
