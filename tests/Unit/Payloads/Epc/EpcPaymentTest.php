<?php

declare(strict_types=1);

use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Qr\Banking\CreditorReference;
use RoundlyConsulting\Qr\Banking\Iban;
use RoundlyConsulting\Qr\Enums\EpcCharset;
use RoundlyConsulting\Qr\Enums\EpcVersion;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Exceptions\InvalidBicException;
use RoundlyConsulting\Qr\Exceptions\InvalidCreditorReferenceException;
use RoundlyConsulting\Qr\Exceptions\InvalidIbanException;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;
use RoundlyConsulting\Qr\Exceptions\PaymentPayloadTooLongException;
use RoundlyConsulting\Qr\Exceptions\UnsupportedCurrencyException;
use RoundlyConsulting\Qr\Facades\Qr;
use RoundlyConsulting\Qr\Payloads\Payments\Epc\EpcPayment;
use RoundlyConsulting\Qr\Testing\MatrixDecoder;

function epcV1(): EpcPayment
{
    return new EpcPayment(
        name: 'Franz Mustermänn',
        iban: 'DE71110220330123456789',
        bic: 'BHBLDEHHXXX',
        amount: Money::ofMinor(1230, 'EUR'),
        purpose: 'GDDS',
        reference: 'RF18539007547034',
        version: EpcVersion::V001,
        charset: EpcCharset::Utf8,
    );
}

function epcV2(): EpcPayment
{
    return new EpcPayment(
        name: "François D'Alsace S.A.",
        iban: 'FR1420041010050500013M02606',
        amount: Money::ofMinor(1230, 'EUR'),
        text: 'Client:Marie Louise La Lune',
        version: EpcVersion::V002,
        charset: EpcCharset::Iso88591,
    );
}

/**
 * The two examples of EPC069-12 v3.1 §2.3, byte for byte, and the QR version they state.
 */
it('reproduces the EPC069-12 v3.1 examples byte for byte at version 6-M', function (Closure $payment, string $fixture, int $bytes): void {
    $expected = (string) file_get_contents(__DIR__."/../../../Fixtures/epc/{$fixture}");
    $matrix = Qr::epc($payment())->matrix();

    expect($payment()->toQrString())->toBe($expected)
        ->and(strlen($expected))->toBe($bytes)
        ->and($matrix->version())->toBe(6)
        ->and($matrix->errorCorrection())->toBe(ErrorCorrection::Medium)
        ->and(MatrixDecoder::decode($matrix)->bytes)->toBe($expected)
        ->and(MatrixDecoder::decode($matrix)->eciDesignator)->toBeNull();
})->with([
    'V1' => [fn () => epcV1(), 'v1-utf8.txt', 96],
    'V2' => [fn () => epcV2(), 'v2-iso-8859-1.txt', 103],
]);

it('pins the example count', function (): void {
    expect(glob(__DIR__.'/../../../Fixtures/epc/*.txt'))->toHaveCount(2);
});

it('parses payloads back, including CRLF separators and other charsets', function (): void {
    foreach (glob(__DIR__.'/../../../Fixtures/epc/*.txt') ?: [] as $file) {
        $payload = (string) file_get_contents($file);

        expect(EpcPayment::fromString($payload)->toQrString())->toBe($payload)
            ->and(EpcPayment::fromString(str_replace("\n", "\r\n", $payload))->toQrString())->toBe($payload);
    }

    $parsed = EpcPayment::fromString((string) file_get_contents(__DIR__.'/../../../Fixtures/epc/v2-iso-8859-1.txt'));

    expect($parsed->name)->toBe("François D'Alsace S.A.")
        ->and($parsed->amount?->minor())->toBe('1230')
        ->and($parsed->bic)->toBeNull()
        ->and($parsed->text)->toBe('Client:Marie Louise La Lune')
        ->and($parsed->charset)->toBe(EpcCharset::Iso88591)
        ->and(EpcPayment::fromString("BCD\n002\n1\nSCT\n\nJana\nSK9611000000002918599669")->amount)->toBeNull()
        ->and(EpcPayment::fromString("BCD\n002\n1\nSCT\n\nJana\nSK9611000000002918599669\n\n\nINV-1")->structuredReference)->toBe('INV-1');
});

it('rejects malformed payloads', function (string $payload, string $class): void {
    expect(fn () => EpcPayment::fromString($payload))->toThrow($class);
})->with([
    ['BCD', InvalidPayloadException::class],
    ["XYZ\n002\n1\nSCT\n\nJana\nSK9611000000002918599669", InvalidPayloadException::class],
    ["BCD\n003\n1\nSCT\n\nJana\nSK9611000000002918599669", InvalidPayloadException::class],
    ["BCD\n002\n9\nSCT\n\nJana\nSK9611000000002918599669", InvalidPayloadException::class],
    ["BCD\n002\n1\nSCT\n\nJana\nSK9611000000002918599669\nUSD1", InvalidPayloadException::class],
    ["BCD\n002\n1\nSCT\n\nJ\xFFna\nSK9611000000002918599669", InvalidPayloadException::class],
    [str_repeat('x', 332), PaymentPayloadTooLongException::class],
]);

it('serialises optional elements and drops trailing empty ones', function (): void {
    $payment = new EpcPayment('Jana Nováková', 'SK96 1100 0000 0029 1859 9669', information: 'Ďakujeme', version: EpcVersion::V002);

    expect($payment->toQrString())->toBe("BCD\n002\n1\nSCT\n\nJana Nováková\nSK9611000000002918599669\n\n\n\n\nĎakujeme")
        ->and((new EpcPayment('Jana', 'SK9611000000002918599669', amount: Money::ofMinor(100, 'EUR')))->toQrString())
        ->toBe("BCD\n002\n1\nSCT\n\nJana\nSK9611000000002918599669\nEUR1")
        ->and((new EpcPayment('Jana', 'SK9611000000002918599669', 'TATRSKBX', reference: CreditorReference::generate('20260042')))->structuredReference)
        ->toBe(CreditorReference::generate('20260042')->electronic());
});

it('collapses separators inside fields and trims them', function (): void {
    $payment = new EpcPayment(" Jana\nNováková\t", 'SK9611000000002918599669', text: "Line 1\r\nLine 2");

    expect($payment->name)->toBe('Jana Nováková')->and($payment->text)->toBe('Line 1 Line 2');
});

it('encodes the 331-byte maximum at version 13 and rejects one byte more', function (): void {
    $base = new EpcPayment('N', 'SK9611000000002918599669', 'TATRSKBX', information: 'x');
    $headerLength = strlen($base->toQrString()) - 1;
    $text = str_repeat('t', 140);
    $info = str_repeat('i', 70);
    $nameLength = 331 - ($headerLength - 1) - 1 - 140 - 1 - 70 - 3;
    $payment = new EpcPayment(str_repeat('n', $nameLength), 'SK9611000000002918599669', 'TATRSKBX', text: $text, information: $info);

    expect(strlen($payment->toQrString()))->toBeLessThanOrEqual(331)
        ->and(Qr::epc($payment)->matrix()->version())->toBeLessThanOrEqual(13);

    $tooLong = new EpcPayment(str_repeat('ž', 70), 'SK9611000000002918599669', 'TATRSKBX', text: str_repeat('ž', 140), information: str_repeat('ž', 70));

    expect(fn () => $tooLong->toQrString())->toThrow(PaymentPayloadTooLongException::class);
});

it('validates fields in the constructor', function (Closure $build, string $class, string $field): void {
    try {
        $build();
    } catch (InvalidPayloadException $e) {
        expect($e)->toBeInstanceOf($class)->and($e->field)->toBe($field);

        return;
    }

    $this->fail('expected a failure');
})->with([
    'name required' => [fn () => new EpcPayment(' ', 'SK9611000000002918599669'), InvalidPayloadException::class, 'name'],
    'name too long' => [fn () => new EpcPayment(str_repeat('a', 71), 'SK9611000000002918599669'), InvalidPayloadException::class, 'name'],
    'iban' => [fn () => new EpcPayment('Jana', 'SK9611000000002918599668'), InvalidIbanException::class, 'iban'],
    'bic' => [fn () => new EpcPayment('Jana', 'SK9611000000002918599669', 'TATR'), InvalidBicException::class, 'bic'],
    'not euro' => [fn () => new EpcPayment('Jana', 'SK9611000000002918599669', amount: Money::ofMinor(100, 'CZK')), UnsupportedCurrencyException::class, 'amount'],
    'custom currency' => [fn () => new EpcPayment('Jana', 'SK9611000000002918599669', amount: Money::ofMinor(1, Currency::custom('PTS', 18))), UnsupportedCurrencyException::class, 'amount'],
    'zero' => [fn () => new EpcPayment('Jana', 'SK9611000000002918599669', amount: Money::ofMinor(0, 'EUR')), InvalidPayloadException::class, 'amount'],
    'negative' => [fn () => new EpcPayment('Jana', 'SK9611000000002918599669', amount: Money::ofMinor(-5, 'EUR')), InvalidPayloadException::class, 'amount'],
    'too large' => [fn () => new EpcPayment('Jana', 'SK9611000000002918599669', amount: Money::ofMinor('100000000000', 'EUR')), InvalidPayloadException::class, 'amount'],
    'purpose' => [fn () => new EpcPayment('Jana', 'SK9611000000002918599669', purpose: 'gdds'), InvalidPayloadException::class, 'purpose'],
    'rf checksum' => [fn () => new EpcPayment('Jana', 'SK9611000000002918599669', reference: 'RF19539007547034'), InvalidCreditorReferenceException::class, 'reference'],
    'reference too long' => [fn () => new EpcPayment('Jana', 'SK9611000000002918599669', reference: str_repeat('1', 36)), InvalidPayloadException::class, 'reference'],
    'text too long' => [fn () => new EpcPayment('Jana', 'SK9611000000002918599669', text: str_repeat('a', 141)), InvalidPayloadException::class, 'text'],
    'information too long' => [fn () => new EpcPayment('Jana', 'SK9611000000002918599669', information: str_repeat('a', 71)), InvalidPayloadException::class, 'information'],
    'reference xor text' => [fn () => new EpcPayment('Jana', 'SK9611000000002918599669', reference: 'INV-1', text: 'x'), InvalidPayloadException::class, 'text'],
]);

it('enforces the version-dependent BIC rule when serialising', function (): void {
    expect(fn () => (new EpcPayment('Jana', 'SK9611000000002918599669', version: EpcVersion::V001))->toQrString())->toThrow(InvalidPayloadException::class, '[bic]')
        ->and(fn () => (new EpcPayment('Swiss', 'CH9300762011623852957', version: EpcVersion::V002))->toQrString())->toThrow(InvalidPayloadException::class, '[bic]')
        ->and((new EpcPayment('Swiss', 'CH9300762011623852957', 'UBSWCHZH80A', version: EpcVersion::V002))->toQrString())->toContain('UBSWCHZH80A')
        ->and(EpcVersion::V002->bicRequiredFor(Iban::fromString('GB29NWBK60161331926819')))->toBeTrue()
        ->and(EpcVersion::V002->bicRequiredFor(Iban::fromString('SK9611000000002918599669')))->toBeFalse();
});

it('checks the declared character set', function (): void {
    expect(fn () => (new EpcPayment('Ďurko Nováková', 'SK9611000000002918599669', charset: EpcCharset::Iso88591))->toQrString())
        ->toThrow(InvalidPayloadException::class, '[name]')
        ->and((new EpcPayment('Jana Nováková', 'SK9611000000002918599669', charset: EpcCharset::Iso88592))->toQrString())->toContain("\nSCT\n")
        ->and(fn () => (new EpcPayment('Jana', 'SK9611000000002918599669', text: 'Ďakujeme €', charset: EpcCharset::Iso88592))->toQrString())
        ->toThrow(InvalidPayloadException::class, '[text]');
});

it('restricts text to the SEPA Latin subset when strict', function (): void {
    expect((new EpcPayment('Jana Novakova', 'SK9611000000002918599669', text: "Invoice (2026/42) + 'x'", strictCharset: true))->toQrString())->toContain('Invoice')
        ->and(fn () => (new EpcPayment('Jana Nováková', 'SK9611000000002918599669', strictCharset: true))->toQrString())->toThrow(InvalidPayloadException::class, '[name]');
});

it('normalises decomposed text to NFC before measuring', function (): void {
    $decomposed = "Nova\u{0301}kova\u{0301}";

    expect((new EpcPayment($decomposed, 'SK9611000000002918599669'))->name)->toBe('Nováková');
});

it('fills unset options from configuration through the manager', function (): void {
    config(['qr.payments.epc.version' => '001', 'qr.payments.epc.charset' => 'iso-8859-2', 'qr.payments.epc.strict_charset' => true]);

    $payload = Qr::epc(new EpcPayment('Jana', 'SK9611000000002918599669', 'TATRSKBX'))->payload();

    expect($payload->toQrString())->toStartWith("BCD\n001\n3\n");

    config(['qr.payments.epc.strict_charset' => false]);

    expect(Qr::epc(epcV2())->payload()->toQrString())->toStartWith("BCD\n002\n2\n");
});

it('locks the spec-mandated encoding options and is personal', function (): void {
    expect(fn () => Qr::epc(epcV1())->errorCorrection('H')->svg())->toThrow(InvalidOptionException::class, '[errorCorrection]')
        ->and(Qr::epc(epcV1())->svg()->sensitivity())->toBe(Sensitivity::Personal)
        ->and(Qr::epc(epcV1())->sensitivity(Sensitivity::Public)->svg()->sensitivity())->toBe(Sensitivity::Public)
        ->and(Qr::epc(epcV1())->info()->errorCorrectionBoosted)->toBeFalse()
        ->and(Qr::epc(epcV1())->svg()->toString())->toContain('<desc>Payment QR code (SEPA credit transfer)</desc>');
});

it('never puts field values into exception messages', function (): void {
    try {
        new EpcPayment('Secret Name', 'SK9611000000002918599669', amount: Money::ofMinor(100, 'USD'));
    } catch (UnsupportedCurrencyException $e) {
        expect($e->getMessage())->not->toContain('Secret')->not->toContain('SK96')->not->toContain('100');

        return;
    }

    $this->fail('expected a failure');
});

it('applies configured defaults through the manager only, as documented', function (): void {
    config(['qr.payments.epc.version' => '001']);
    $payment = new EpcPayment('A', 'SK9611000000002918599669', 'TATRSKBX');

    expect(explode("\n", Qr::epc($payment)->payload()->toQrString())[1])->toBe('001')
        ->and(explode("\n", $payment->toQrString())[1])->toBe('002');
});

it('rejects a purpose code with a trailing newline instead of shifting the reference line', function (): void {
    try {
        new EpcPayment('Acme', 'DE89370400440532013000', purpose: "GDDS\n", reference: 'RF18539007547034');
        $this->fail('expected a failure');
    } catch (InvalidPayloadException $e) {
        expect($e->field)->toBe('purpose');
    }

    expect((new EpcPayment('Acme', 'DE89370400440532013000', purpose: 'GDDS', reference: 'RF18539007547034'))->toQrString())
        ->toEndWith("\nGDDS\nRF18539007547034");
});

it('rejects text that is not valid UTF-8 instead of emitting a payload it cannot parse', function (Closure $build, string $field): void {
    try {
        $build();
        $this->fail('expected a failure');
    } catch (InvalidPayloadException $e) {
        expect($e->field)->toBe($field)->and($e->reason)->toBe(InvalidPayloadException::REASON_INVALID_FORMAT);
    }
})->with([
    'name' => [fn () => new EpcPayment("J\xE1n", 'SK9611000000002918599669'), 'name'],
    'text' => [fn () => new EpcPayment('Jan', 'SK9611000000002918599669', text: "Fakt\xFAra"), 'text'],
    'information' => [fn () => new EpcPayment('Jan', 'SK9611000000002918599669', information: "\xFF"), 'information'],
    'reference' => [fn () => new EpcPayment('Jan', 'SK9611000000002918599669', reference: "INV\xE1"), 'reference'],
]);

it('parses a full 12-element payload with one trailing separator like a shorter one', function (): void {
    $payload = "BCD\n002\n1\nSCT\nTATRSKBX\nJana\nSK9611000000002918599669\nEUR1\nGDDS\n\nThanks\nInfo";
    $expected = EpcPayment::fromString($payload)->toQrString();

    expect($expected)->toBe($payload)
        ->and(EpcPayment::fromString($payload."\n")->toQrString())->toBe($expected)
        ->and(EpcPayment::fromString(str_replace("\n", "\r\n", $payload)."\r\n")->toQrString())->toBe($expected)
        ->and(fn () => EpcPayment::fromString($payload."\n\n"))->toThrow(InvalidPayloadException::class, '[payload]');
});
