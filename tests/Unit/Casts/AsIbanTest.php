<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Qr\Banking\Iban;
use RoundlyConsulting\Qr\Casts\AsIban;
use RoundlyConsulting\Qr\Exceptions\InvalidIbanException;

function ibanModel(): Model
{
    return new class extends Model
    {
        protected $guarded = [];

        protected function casts(): array
        {
            return ['iban' => AsIban::class];
        }
    };
}

it('stores the electronic form and reads an Iban back — no database needed', function (): void {
    $model = ibanModel();
    $model->setAttribute('iban', 'sk96 1100 0000 0029 1859 9669');

    expect($model->getAttributes()['iban'])->toBe('SK9611000000002918599669')
        ->and($model->getAttribute('iban'))->toBeInstanceOf(Iban::class);

    $model->setAttribute('iban', Iban::fromString('DE71110220330123456789'));
    expect($model->getAttributes()['iban'])->toBe('DE71110220330123456789');

    $model->setRawAttributes(['iban' => 'AT611904300234573201']);
    expect($model->getAttribute('iban')?->formatted())->toBe('AT61 1904 3002 3457 3201');
});

it('maps empty values to null', function (mixed $value): void {
    $model = ibanModel();
    $model->setAttribute('iban', $value);

    expect($model->getAttributes()['iban'])->toBeNull()
        ->and($model->getAttribute('iban'))->toBeNull();
})->with([null, '']);

it('refuses invalid IBANs', function (): void {
    ibanModel()->setAttribute('iban', 'SK9611000000002918599668');
})->throws(InvalidIbanException::class);

it('refuses a value that is not a string with InvalidIbanException, not a TypeError', function (mixed $value): void {
    try {
        ibanModel()->setAttribute('iban', $value);
        $this->fail('expected a failure');
    } catch (InvalidIbanException $e) {
        expect($e->reason)->toBe('iban_format');
    }
})->with([
    'int' => [12345],
    'float' => [12.5],
    'bool' => [true],
    'array' => [['x']],
    'object' => [new stdClass],
]);
