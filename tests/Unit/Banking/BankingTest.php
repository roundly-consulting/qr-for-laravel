<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Qr\Banking\Bic;
use RoundlyConsulting\Qr\Banking\CountryCodes;
use RoundlyConsulting\Qr\Banking\CreditorReference;
use RoundlyConsulting\Qr\Banking\Eea;
use RoundlyConsulting\Qr\Banking\Iban;
use RoundlyConsulting\Qr\Banking\IbanRegistry;
use RoundlyConsulting\Qr\Banking\Mod97;
use RoundlyConsulting\Qr\Exceptions\InvalidBicException;
use RoundlyConsulting\Qr\Exceptions\InvalidCreditorReferenceException;
use RoundlyConsulting\Qr\Exceptions\InvalidIbanException;
use RoundlyConsulting\Qr\Rules\CreditorReference as CreditorReferenceRule;
use RoundlyConsulting\Qr\Rules\Iban as IbanRule;

it('computes ISO 7064 MOD 97-10 remainders', function (): void {
    expect(Mod97::remainder('11000000002918599669'.'2820'.'96'))->toBe(1)
        ->and(Mod97::remainder('ABC'))->toBe(101112 % 97)
        ->and(Mod97::remainder('0'))->toBe(0);
});

it('pins the registry and keeps every entry consistent', function (): void {
    expect(IbanRegistry::COUNTRIES)->toHaveCount(89)
        ->and(IbanRegistry::RELEASE)->toBe('2026-09')
        ->and(IbanRegistry::has('XX'))->toBeFalse()
        ->and(IbanRegistry::length('XX'))->toBeNull()
        ->and(IbanRegistry::pattern('XX'))->toBeNull()
        ->and(IbanRegistry::pattern('SK'))->toBe('/^[0-9]{4}[0-9]{6}[0-9]{10}$/');

    foreach (IbanRegistry::COUNTRIES as $country => [$length, $structure]) {
        preg_match_all('/(\d+)!([nac])/', $structure, $parts, PREG_SET_ORDER);
        $bban = '';

        foreach ($parts as [, $count, $kind]) {
            $bban .= str_repeat(['n' => '7', 'a' => 'Q', 'c' => 'Z'][$kind], (int) $count);
        }

        $check = str_pad((string) (98 - Mod97::remainder($bban.$country.'00')), 2, '0', STR_PAD_LEFT);

        expect(strlen($bban) + 4)->toBe($length)
            ->and(CountryCodes::exists($country))->toBeTrue()
            ->and(Iban::fromString($country.$check.$bban)->electronic())->toBe($country.$check.$bban);
    }
});

it('accepts valid IBANs in electronic and print form', function (string $value): void {
    expect(Iban::isValid($value))->toBeTrue();
})->with([
    'SK9611000000002918599669', 'SK5681800000007000157042', 'CZ6508000000192000145399', 'AT611904300234573201',
    'DE71110220330123456789', 'BE72000000001616', 'FR1420041010050500013M02606', 'CH9300762011623852957',
    'GB29NWBK60161331926819', 'SK96 1100 0000 0029 1859 9669', "sk96\u{00A0}1100-0000-0029-1859-9669",
    'registry 103: Honduras' => 'HN88CABF00000000000250005469',
    'registry 103: Yemen' => 'YE15CBYE0001018861234567891234',
    'registry 103: Brazil alphanumeric bank code' => 'BR6699999A03000010009795493C1',
]);

it('rejects invalid IBANs with a reason', function (string $value, string $reason): void {
    try {
        Iban::fromString($value);
    } catch (InvalidIbanException $e) {
        expect($e->reason)->toBe($reason);

        if ($value !== '') {
            expect($e->getMessage())->not->toContain($value);
        }

        return;
    }

    $this->fail('expected a failure');
})->with([
    ['SK9611000000002918599668', 'iban_checksum'],
    ['CZ6508000000192000145398', 'iban_checksum'],
    ['AT611904300234573202', 'iban_checksum'],
    ['SK96110000000029185996', 'iban_length'],
    ['XX9611000000002918599669', 'iban_country'],
    'territory folded into FR' => ['GF4120041010050500013M02606', 'iban_country'],
    'territory folded into GB' => ['JE90NWBK60161331926819', 'iban_country'],
    'territory folded into FI' => ['AX2112345600000785', 'iban_country'],
    ['SK961100000000291859966A', 'iban_format'],
    ['', 'iban_format'],
    ['1234', 'iban_format'],
]);

it('exposes IBAN parts', function (): void {
    $iban = Iban::fromString('sk96 1100 0000 0029 1859 9669');

    expect($iban->country())->toBe('SK')
        ->and($iban->checkDigits())->toBe('96')
        ->and($iban->bban())->toBe('11000000002918599669')
        ->and($iban->electronic())->toBe('SK9611000000002918599669')
        ->and($iban->formatted())->toBe('SK96 1100 0000 0029 1859 9669')
        ->and((string) $iban)->toBe('SK9611000000002918599669')
        ->and($iban->isEea())->toBeTrue()
        ->and(Iban::fromString('CH9300762011623852957')->isEea())->toBeFalse()
        ->and($iban->equals(Iban::fromString('SK9611000000002918599669')))->toBeTrue()
        ->and(Iban::isValid('nope'))->toBeFalse()
        ->and(Eea::contains('NO'))->toBeTrue()
        ->and(Eea::COUNTRIES)->toHaveCount(30);
});

it('parses BICs', function (): void {
    $bic = Bic::fromString('tatr skbx');
    $branch = Bic::fromString('BHBLDEHHXXX');

    expect($bic->value())->toBe('TATRSKBX')
        ->and((string) $bic)->toBe('TATRSKBX')
        ->and($bic->institution())->toBe('TATR')
        ->and($bic->country())->toBe('SK')
        ->and($bic->location())->toBe('BX')
        ->and($bic->branch())->toBeNull()
        ->and($branch->branch())->toBe('XXX')
        ->and($bic->isTestCode())->toBeFalse()
        ->and(Bic::fromString('ABCDUS30')->isTestCode())->toBeTrue()
        ->and(Bic::isValid('BPOTBEB1'))->toBeTrue()
        ->and(Bic::isValid('BPOT'))->toBeFalse();
});

it('rejects invalid BICs', function (string $value, string $reason): void {
    expect(fn () => Bic::fromString($value))->toThrow(InvalidBicException::class);

    try {
        Bic::fromString($value);
    } catch (InvalidBicException $e) {
        expect($e->reason)->toBe($reason);
    }
})->with([['TATRSKB', 'bic_format'], ['TATRSKBXX', 'bic_format'], ['1ATRSKBX', 'bic_format'], ['TATRQQBX', 'bic_country']]);

it('validates and generates ISO 11649 creditor references', function (): void {
    $reference = CreditorReference::fromString('rf18 5390 0754 7034');

    expect($reference->electronic())->toBe('RF18539007547034')
        ->and($reference->formatted())->toBe('RF18 5390 0754 7034')
        ->and((string) $reference)->toBe('RF18539007547034')
        ->and(CreditorReference::generate('539007547034')->electronic())->toBe('RF18539007547034')
        ->and(CreditorReference::generate('2026 0042')->electronic())->toBe(CreditorReference::fromString(CreditorReference::generate('20260042')->electronic())->electronic())
        ->and(CreditorReference::isValid('RF19539007547034'))->toBeFalse()
        ->and(CreditorReference::isValid('RF18539007547034'))->toBeTrue();

    foreach (['1', 'A', 'INVOICE2026', str_repeat('9', 21)] as $raw) {
        expect(CreditorReference::isValid(CreditorReference::generate($raw)->electronic()))->toBeTrue();
    }
});

it('rejects malformed creditor references', function (Closure $build, string $reason): void {
    try {
        $build();
    } catch (InvalidCreditorReferenceException $e) {
        expect($e->reason)->toBe($reason);

        return;
    }

    $this->fail('expected a failure');
})->with([
    [fn () => CreditorReference::fromString('XX18539007547034'), 'creditor_reference_format'],
    [fn () => CreditorReference::fromString('RF18'.str_repeat('1', 22)), 'creditor_reference_format'],
    [fn () => CreditorReference::fromString('RF19539007547034'), 'creditor_reference_checksum'],
    [fn () => CreditorReference::generate(''), 'creditor_reference_format'],
    [fn () => CreditorReference::generate('a-b'), 'creditor_reference_format'],
]);

it('rejects check digits 00, 01 and 99 that no issuer can produce', function (): void {
    foreach (['DE00370400440000000060', 'DE01370400440000000042', 'DE99370400440000000024'] as $iban) {
        try {
            Iban::fromString($iban);
            $this->fail("expected {$iban} to fail");
        } catch (InvalidIbanException $e) {
            expect($e->reason)->toBe('iban_checksum');
        }

        expect(Validator::make(['iban' => $iban], ['iban' => [new IbanRule]])->passes())->toBeFalse();
    }

    foreach (['RF0072', 'RF0154', 'RF9936'] as $reference) {
        try {
            CreditorReference::fromString($reference);
            $this->fail("expected {$reference} to fail");
        } catch (InvalidCreditorReferenceException $e) {
            expect($e->reason)->toBe('creditor_reference_checksum');
        }

        expect(Validator::make(['ref' => $reference], ['ref' => [new CreditorReferenceRule]])->passes())->toBeFalse();
    }

    expect(Iban::isValid('DE89370400440532013000'))->toBeTrue()
        ->and(CreditorReference::isValid('RF18539007547034'))->toBeTrue();
});
