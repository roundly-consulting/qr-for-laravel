<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Qr\Rules\Bic;
use RoundlyConsulting\Qr\Rules\CreditorReference;
use RoundlyConsulting\Qr\Rules\Iban;

it('validates banking identifiers with translated messages', function (object $rule, mixed $value, ?string $message): void {
    $validator = Validator::make(['field' => $value], ['field' => [$rule]]);

    expect($validator->passes())->toBe($message === null);

    if ($message !== null) {
        expect($validator->errors()->first('field'))->toBe($message);
    }
})->with([
    [new Iban, 'SK96 1100 0000 0029 1859 9669', null],
    [new Iban, 'SK9611000000002918599668', 'The field must be a valid IBAN.'],
    [new Iban, 42, 'The field must be a valid IBAN.'],
    [new Iban(eeaOnly: true), 'DE71110220330123456789', null],
    [new Iban(eeaOnly: true), 'CH9300762011623852957', 'The field must be an IBAN from a country of the European Economic Area.'],
    [new Bic, 'TATRSKBX', null],
    [new Bic, 'TATR', 'The field must be a valid BIC.'],
    [new Bic, null, 'The field must be a valid BIC.'],
    [new CreditorReference, 'RF18539007547034', null],
    [new CreditorReference, 'RF00539007547034', 'The field must be a valid creditor reference (RF).'],
    [new CreditorReference, ['x'], 'The field must be a valid creditor reference (RF).'],
]);
