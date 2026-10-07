<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Qr\Compression\Lzma\LzmaEncoder;
use RoundlyConsulting\Qr\Enums\BySquare\BySquareVersion;
use RoundlyConsulting\Qr\Enums\BySquare\DirectDebitScheme;
use RoundlyConsulting\Qr\Enums\BySquare\DirectDebitType;
use RoundlyConsulting\Qr\Enums\BySquare\Month;
use RoundlyConsulting\Qr\Enums\BySquare\PaymentType;
use RoundlyConsulting\Qr\Enums\BySquare\Periodicity;
use RoundlyConsulting\Qr\Enums\EciMode;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\Mode;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Exceptions\BySquareDecodeException;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;
use RoundlyConsulting\Qr\Exceptions\PaymentPayloadTooLongException;
use RoundlyConsulting\Qr\Facades\Qr;
use RoundlyConsulting\Qr\Payloads\Payments\BySquare\BankAccount;
use RoundlyConsulting\Qr\Payloads\Payments\BySquare\Base32Hex;
use RoundlyConsulting\Qr\Payloads\Payments\BySquare\Beneficiary;
use RoundlyConsulting\Qr\Payloads\Payments\BySquare\BySquareCodec;
use RoundlyConsulting\Qr\Payloads\Payments\BySquare\BySquareSerializer;
use RoundlyConsulting\Qr\Payloads\Payments\BySquare\Deburr;
use RoundlyConsulting\Qr\Payloads\Payments\BySquare\DirectDebitDetails;
use RoundlyConsulting\Qr\Payloads\Payments\BySquare\PayBySquare;
use RoundlyConsulting\Qr\Payloads\Payments\BySquare\Payment;
use RoundlyConsulting\Qr\Payloads\Payments\BySquare\StandingOrderDetails;
use RoundlyConsulting\Qr\Testing\MatrixDecoder;

const SK_IBAN = 'SK9611000000002918599669';

/**
 * The models behind the committed serialised vectors.
 *
 * @return array<string, PayBySquare>
 */
function serializedModels(): array
{
    return [
        'payment order' => new PayBySquare([Payment::order(
            amount: Money::ofMinor(12550, 'EUR'),
            accounts: [new BankAccount(SK_IBAN, 'TATRSKBX')],
            beneficiary: new Beneficiary('Jana Nováková', 'Hlavná 1', 'Košice'),
            dueDate: CarbonImmutable::parse('2026-10-15'),
            variableSymbol: '20260042',
            constantSymbol: '0308',
            note: 'Faktúra 2026-0042',
        )], 'qr-0001'),
        'standing order' => new PayBySquare([Payment::standingOrder(
            new StandingOrderDetails(Periodicity::Monthly, 15, [], CarbonImmutable::parse('2027-12-31')),
            Money::ofMinor(3000, 'EUR'),
            [new BankAccount(SK_IBAN)],
            new Beneficiary('Útulok Labka'),
        )], 'qr-0002'),
        'direct debit' => new PayBySquare([Payment::directDebit(
            new DirectDebitDetails(variableSymbol: '20260099'),
            Money::ofMinor(1290, 'EUR'),
            [new BankAccount(SK_IBAN)],
            new Beneficiary('VetClinic s.r.o.'),
        )], 'qr-0003'),
    ];
}

function bsqOrder(?Beneficiary $beneficiary = new Beneficiary('Jana'), ?Money $amount = null, array $extra = []): Payment
{
    return Payment::order(...['amount' => $amount ?? Money::ofMinor(100, 'EUR'), 'accounts' => [new BankAccount(SK_IBAN)], 'beneficiary' => $beneficiary, ...$extra]);
}

it('serialises byte for byte like an independent implementation', function (string $name): void {
    $expected = (require __DIR__.'/../../../Fixtures/bysquare/serialized-vectors.php')[$name];

    expect(serializedModels()[$name]->serialize())->toBe($expected)
        ->and(PayBySquare::decode(serializedModels()[$name]->encode())->serialize())->toBe($expected);
})->with(['payment order', 'standing order', 'direct debit']);

it('pins the vector counts', function (): void {
    expect(require __DIR__.'/../../../Fixtures/bysquare/serialized-vectors.php')->toHaveCount(3)
        ->and(require __DIR__.'/../../../Fixtures/bysquare/wire-vectors.php')->toHaveCount(4);
});

it('decodes strings from an independent encoder', function (string $name): void {
    $vector = (require __DIR__.'/../../../Fixtures/bysquare/wire-vectors.php')[$name];
    $frame = BySquareCodec::decode($vector['code']);
    $document = PayBySquare::decode($vector['code']);

    expect($frame->version)->toBe(BySquareVersion::V1_2_0)
        ->and($frame->serialized)->toBe($vector['serialized'])
        ->and($document->serialize())->toBe($vector['serialized'])
        ->and(PayBySquare::decode($document->encode())->serialize())->toBe($vector['serialized']);
})->with(fn (): array => array_keys(require __DIR__.'/../../../Fixtures/bysquare/wire-vectors.php'));

it('decodes 1.0.0 strings that carry the beneficiary block', function (string $code, BySquareVersion $version, ?string $name, string $serialized): void {
    // An independent encoder writes the (1.1.0) beneficiary block for every version,
    // 1.0.0 included; such codes are in circulation and must still decode.
    $document = PayBySquare::decode($code);

    expect(BySquareCodec::decode($code)->version)->toBe(BySquareVersion::V1_0_0)
        ->and($document->version)->toBe($version)
        ->and($document->payments[0]->beneficiary?->name)->toBe($name)
        ->and($document->payments[0]->variableSymbol)->toBe('123')
        ->and($document->serialize())->toBe($serialized);
})->with([
    'empty block' => [
        '0003M0004IF4SJM09C97P9SG2S5I1KTBQDO503HBOG9V53NAS8149G30KTHEIUNTP8JL6O3URDRGV0SR8TG578047VVPSH0000',
        BySquareVersion::V1_0_0,
        null,
        "\t1\t1\t25\tEUR\t\t123\t\t\t\t\t1\tSK9611000000002918599669\t\t0\t0",
    ],
    'beneficiary in the block' => [
        '0005A0006IHCD053NTRQ95GN6HB1UHBK3300MBHBNOB3DJ31FLL1C7RJ7SFTD0R6QVBQOTHDI7ND6TM66C3DJUBEKQUSGC20PN1F470F74LL22UIK66MH52645IVO90CVV2QQK00',
        BySquareVersion::V1_1_0,
        'Jana Novakova',
        "qr-0005\t1\t1\t25\tEUR\t\t123\t\t\t\t\t1\tSK9611000000002918599669\t\t0\t0\tJana Novakova\t\tKosice",
    ],
]);

it('still rejects a 1.0.0 frame with a partial trailing block', function (): void {
    PayBySquare::decode(BySquareCodec::encode("\t1\t1\t25\tEUR\t\t123\t\t\t\t\t1\tSK9611000000002918599669\t\t0\t0\tJana", BySquareVersion::V1_0_0));
})->throws(BySquareDecodeException::class);

it('restores the full model from a decoded string', function (): void {
    $vectors = require __DIR__.'/../../../Fixtures/bysquare/wire-vectors.php';

    $standing = PayBySquare::decode($vectors['standing order']['code'])->payments[0];
    $debit = PayBySquare::decode($vectors['direct debit']['code'])->payments[0];
    $order = PayBySquare::decode($vectors['payment order with invoice id']['code']);

    expect($standing->type)->toBe(PaymentType::StandingOrder)
        ->and($standing->standingOrder?->months)->toBe([Month::January, Month::July])
        ->and($standing->standingOrder?->periodicity)->toBe(Periodicity::Quarterly)
        ->and($standing->beneficiary?->city)->toBe('Kosice')
        ->and($debit->directDebit?->scheme)->toBe(DirectDebitScheme::Sepa)
        ->and($debit->directDebit?->type)->toBe(DirectDebitType::Recurrent)
        ->and($debit->directDebit?->maxAmount?->minor())->toBe('5000')
        ->and($debit->directDebit?->validTill?->format('Y-m-d'))->toBe('2028-12-31')
        ->and($debit->amount?->minor())->toBe('1999')
        ->and($order->invoiceId)->toBe('qr-0001')
        ->and($order->payments[0]->dueDate?->format('Ymd'))->toBe('20261015')
        ->and($order->payments[0]->accounts[0]->bic?->value())->toBe('TATRSKBX')
        ->and($order->payments[0]->constantSymbol)->toBe('0308');
});

it('writes the version into the header nibble', function (BySquareVersion $version, string $prefix): void {
    $beneficiary = $version === BySquareVersion::V1_0_0 ? null : new Beneficiary('Jana');
    $document = new PayBySquare([bsqOrder($beneficiary)], version: $version);

    expect($document->encode())->toStartWith($prefix)
        ->and(PayBySquare::decode($document->encode())->version)->toBe($version);
})->with([
    [BySquareVersion::V1_0_0, '00'],
    [BySquareVersion::V1_1_0, '04'],
    [BySquareVersion::V1_2_0, '08'],
]);

it('round-trips every payment type, several payments and accounts', function (): void {
    $document = new PayBySquare([
        Payment::order(null, [new BankAccount(SK_IBAN, 'TATRSKBX'), new BankAccount('CZ6508000000192000145399')], new Beneficiary('Jana'), currency: 'czk', note: "Dar\tna útulok"),
        Payment::standingOrder(new StandingOrderDetails(Periodicity::Weekly, 5, [Month::March], null), Money::ofMinor(5, 'JPY'), [new BankAccount(SK_IBAN)], new Beneficiary('Ján Kováč', 'Štúrova 27', 'Košice')),
        Payment::directDebit(new DirectDebitDetails(DirectDebitScheme::Other, DirectDebitType::OneOff, '1', '2', 'ref', 'M', 'C', 'K', Money::ofMinor(9, 'EUR'), CarbonImmutable::parse('2030-01-01')), Money::ofMinor(0, 'EUR'), [new BankAccount(SK_IBAN)], new Beneficiary('X'), CarbonImmutable::parse('2026-02-28'), '123', '0558', '999', 'orig'),
    ], 'multi');

    $decoded = PayBySquare::decode($document->encode());

    expect($decoded->serialize())->toBe($document->serialize())
        ->and($document->serialize())->toContain('Dar na utulok')
        ->and($document->serialize())->toContain("Jan Kovac\tSturova 27\tKosice")
        ->and($decoded->payments[0]->currency)->toBe('CZK')
        ->and($decoded->payments[0]->amount)->toBeNull()
        ->and(count($decoded->payments[0]->accounts))->toBe(2);
});

it('allows a daily standing order without an execution day', function (): void {
    $details = new StandingOrderDetails(Periodicity::Daily);
    $document = new PayBySquare([Payment::standingOrder($details, Money::ofMinor(100, 'EUR'), [new BankAccount(SK_IBAN)], new Beneficiary('Jana'))]);

    expect(PayBySquare::decode($document->encode())->payments[0]->standingOrder?->day)->toBeNull();
});

it('keeps diacritics when deburring is off', function (): void {
    $document = new PayBySquare([bsqOrder(new Beneficiary('Ján Kováč'), extra: ['note' => 'Príspevok na kávu'])], deburr: false);

    expect($document->serialize())->toContain('Príspevok na kávu')->toContain('Ján Kováč')
        ->and(Deburr::apply('Príspevok na kávu'))->toBe('Prispevok na kavu');
});

it('encodes as one alphanumeric segment with locked options', function (): void {
    $pending = Qr::payBySquare(serializedModels()['payment order']);
    $matrix = $pending->matrix();
    $decoded = MatrixDecoder::decode($matrix);

    expect($decoded->bytes)->toBe(serializedModels()['payment order']->encode())
        ->and($decoded->segments)->toHaveCount(1)
        ->and($decoded->segments[0]->mode)->toBe(Mode::Alphanumeric)
        ->and($decoded->eciDesignator)->toBeNull()
        ->and($pending->svg()->sensitivity())->toBe(Sensitivity::Personal)
        ->and($pending->svg()->toString())->toContain('<desc>Payment QR code (PAY by square)</desc>')
        ->and($pending->errorCorrection(ErrorCorrection::Quartile)->matrix()->errorCorrection())->toBeIn([ErrorCorrection::Quartile, ErrorCorrection::High])
        ->and(fn () => $pending->eci(EciMode::Always)->matrix())->toThrow(InvalidOptionException::class, '[eci]');
});

it('fills version and deburr from configuration through the manager', function (): void {
    config(['qr.payments.bysquare.version' => '1.1.0', 'qr.payments.bysquare.deburr' => false]);

    $payload = Qr::payBySquare(new PayBySquare([bsqOrder(new Beneficiary('Ján'))]))->payload();

    expect($payload->toQrString())->toStartWith('04')
        ->and(PayBySquare::decode($payload->toQrString())->serialize())->toContain('Ján')
        ->and(Qr::make(new PayBySquare([bsqOrder(new Beneficiary('Ján'))]))->payload()->toQrString())->toStartWith('04');
});

it('enforces version rules when encoding', function (): void {
    expect(fn () => (new PayBySquare([bsqOrder(new Beneficiary('Jana'))], version: BySquareVersion::V1_0_0))->encode())
        ->toThrow(InvalidPayloadException::class, 'not supported by version 1.0.0')
        ->and(fn () => (new PayBySquare([bsqOrder(null)]))->encode())->toThrow(InvalidPayloadException::class, '[beneficiary.name]')
        ->and((new PayBySquare([bsqOrder(null)], version: BySquareVersion::V1_1_0))->encode())->toStartWith('04');
});

it('re-checks lengths after deburring', function (): void {
    $name = str_repeat('ß', 40);

    expect(fn () => (new PayBySquare([bsqOrder(new Beneficiary($name))]))->encode())->toThrow(InvalidPayloadException::class, '[beneficiary.name]')
        ->and((new PayBySquare([bsqOrder(new Beneficiary($name))], deburr: false))->encode())->toStartWith('08');
});

it('validates payment fields', function (Closure $build, string $field): void {
    try {
        $build();
    } catch (InvalidPayloadException $e) {
        expect($e->field)->toBe($field);

        return;
    }

    $this->fail('expected a failure');
})->with([
    [fn () => new PayBySquare([]), 'payments'],
    [fn () => new PayBySquare([bsqOrder()], str_repeat('i', 11)), 'invoiceId'],
    [fn () => Payment::order(null, [new BankAccount(SK_IBAN)]), 'currency'],
    [fn () => Payment::order(null, [new BankAccount(SK_IBAN)], currency: 'EU'), 'currency'],
    [fn () => Payment::order(null, [new BankAccount(SK_IBAN)], currency: 'XYZ'), 'currency'],
    [fn () => Payment::order(Money::ofMinor(100, 'EUR'), [new BankAccount(SK_IBAN)], currency: 'CZK'), 'currency'],
    [fn () => Payment::order(Money::ofMinor(1, Currency::custom('PTS', 18)), [new BankAccount(SK_IBAN)]), 'amount'],
    [fn () => Payment::order(Money::ofMinor(-1, 'EUR'), [new BankAccount(SK_IBAN)]), 'amount'],
    [fn () => Payment::order(Money::ofMinor('1234567890123456', 'EUR'), [new BankAccount(SK_IBAN)]), 'amount'],
    [fn () => Payment::order(Money::ofMinor(1, 'EUR'), []), 'accounts'],
    [fn () => bsqOrder(extra: ['variableSymbol' => '12345678901']), 'variableSymbol'],
    [fn () => bsqOrder(extra: ['constantSymbol' => '12345']), 'constantSymbol'],
    [fn () => bsqOrder(extra: ['specificSymbol' => 'abc']), 'specificSymbol'],
    [fn () => bsqOrder(extra: ['originatorsReference' => str_repeat('r', 36)]), 'originatorsReference'],
    [fn () => bsqOrder(extra: ['note' => str_repeat('n', 141)]), 'note'],
    [fn () => new Beneficiary(' '), 'beneficiary.name'],
    [fn () => new Beneficiary('Jana', str_repeat('s', 71)), 'beneficiary.street'],
    [fn () => new StandingOrderDetails(Periodicity::Weekly, 8), 'standingOrder.day'],
    [fn () => new StandingOrderDetails(Periodicity::Monthly, 32), 'standingOrder.day'],
    [fn () => new StandingOrderDetails(Periodicity::Daily, 1), 'standingOrder.day'],
    [fn () => new DirectDebitDetails(variableSymbol: 'x'), 'directDebit.variableSymbol'],
    [fn () => new DirectDebitDetails(originatorsReference: str_repeat('o', 36)), 'directDebit.originatorsReference'],
    [fn () => new DirectDebitDetails(mandateId: str_repeat('m', 36)), 'directDebit.mandateId'],
    [fn () => Payment::directDebit(new DirectDebitDetails(maxAmount: Money::ofMinor(1, 'CZK')), Money::ofMinor(1, 'EUR'), [new BankAccount(SK_IBAN)]), 'directDebit.maxAmount'],
    [fn () => new BankAccount(SK_IBAN, 'TATR'), 'bic'],
]);

it('rejects oversized data models', function (): void {
    BySquareCodec::encode(str_repeat('x', 65532), BySquareVersion::V1_2_0);
})->throws(PaymentPayloadTooLongException::class);

it('encodes and decodes base32hex', function (): void {
    expect(Base32Hex::encode(''))->toBe('')
        ->and(Base32Hex::encode('f'))->toBe('CO')
        ->and(Base32Hex::encode('foobar'))->toBe('CPNMUOJ1E8')
        ->and(Base32Hex::decode('cpnmuoj1e8=='))->toBe('foobar')
        ->and(Base32Hex::decode('C'))->toBe('');
});

it('names the failing stage when decoding', function (string $code, string $reason): void {
    try {
        PayBySquare::decode($code);
    } catch (BySquareDecodeException $e) {
        expect($e->reason)->toBe($reason);

        return;
    }

    $this->fail('expected a failure');
})->with([
    'bad alphabet' => ['0804WXYZ', 'bysquare_base32hex'],
    'empty' => ['', 'bysquare_base32hex'],
    'too long' => [str_repeat('0', 4297), 'bysquare_base32hex'],
    'too short' => ['0804', 'bysquare_header'],
    'wrong type' => [Base32Hex::encode("\x12\x00\x10\x00".str_repeat("\x00", 8)), 'bysquare_header'],
    'unknown version' => [Base32Hex::encode("\x03\x00\x10\x00".str_repeat("\x00", 8)), 'bysquare_header'],
    'document type' => [Base32Hex::encode("\x02\x10\x10\x00".str_repeat("\x00", 8)), 'bysquare_header'],
    'length' => [Base32Hex::encode("\x02\x00\x02\x00".str_repeat("\x00", 8)), 'bysquare_length'],
    'lzma' => [Base32Hex::encode("\x02\x00\x10\x00\x01\x02\x03\x04\x05\x06"), 'bysquare_lzma'],
    'crc' => [tampered('crc'), 'bysquare_crc'],
    'fields' => [tampered('fields'), 'bysquare_fields'],
    'trailing fields' => [tampered('trailing'), 'bysquare_fields'],
    'bad value' => [tampered('value'), 'bysquare_value'],
    'bad date' => [tampered('date'), 'bysquare_value'],
    'bad utf-8' => [tampered('utf8'), 'bysquare_fields'],
]);

/**
 * A structurally valid frame around a hand-made (and deliberately broken) data model.
 */
function tampered(string $kind): string
{
    $valid = "\t1\t1\t1\tEUR\t\t\t\t\t\t\t1\t".SK_IBAN."\t\t0\t0\tJana\t\t";

    $serialized = match ($kind) {
        'fields' => "\t1\t1\t1\tEUR",
        'trailing' => $valid."\textra",
        'value' => str_replace("\t1\tEUR", "\t-1\tEUR", $valid),
        'date' => str_replace("EUR\t\t", "EUR\t20260230\t", $valid),
        'utf8' => $valid."\xFF",
        default => $valid,
    };

    $checked = pack('V', crc32($serialized)).$serialized;

    if ($kind === 'crc') {
        $checked[0] = chr(ord($checked[0]) ^ 0xFF);
    }

    return Base32Hex::encode("\x02\x00".pack('v', strlen($checked)).LzmaEncoder::encode($checked));
}

it('rejects unknown enum values and malformed numbers in the data model', function (string $serialized): void {
    $checked = pack('V', crc32($serialized)).$serialized;
    $code = Base32Hex::encode("\x02\x00".pack('v', strlen($checked)).LzmaEncoder::encode($checked));

    expect(fn () => PayBySquare::decode($code))->toThrow(BySquareDecodeException::class);
})->with([
    "\t1\t3\t1\tEUR\t\t\t\t\t\t\t1\t".SK_IBAN."\t\t0\t0\tJana\t\t",
    "\t1\t1\t1\tEUR\t\t\t\t\t\t\t1\t".SK_IBAN."\t\t2\t0\tJana\t\t",
    "\t1\t2\t1\tEUR\t\t\t\t\t\t\t1\t".SK_IBAN."\t\t1\t\t\tx\t\t0\tJana\t\t",
    "\t1\t2\t1\tEUR\t\t\t\t\t\t\t1\t".SK_IBAN."\t\t0\t0\tJana\t\t",
    "\t1\t4\t1\tEUR\t\t\t\t\t\t\t1\t".SK_IBAN."\t\t0\t0\tJana\t\t",
    "\t1\t4\t1\tEUR\t\t\t\t\t\t\t1\t".SK_IBAN."\t\t0\t1\t2\t\t\t\t\t\t\t\t\t\tJana\t\t",
    "\t1\t4\t1\tEUR\t\t\t\t\t\t\t1\t".SK_IBAN."\t\t0\t1\t\t2\t\t\t\t\t\t\t\t\tJana\t\t",
    "\t1\t1\t1.2.3\tEUR\t\t\t\t\t\t\t1\t".SK_IBAN."\t\t0\t0\tJana\t\t",
    "\t1\t1\t1\tXYZ\t\t\t\t\t\t\t1\t".SK_IBAN."\t\t0\t0\tJana\t\t",
    "\t1\t1\t1\tEUR\t\t\t\t\t\t\t0\t0\t0\tJana\t\t",
    "\t1\t1\t1\tEUR\t\t\t\t\t\t\t1\tSK00\t\t0\t0\tJana\t\t",
]);

it('applies configured defaults through the manager only, as documented', function (): void {
    config(['qr.payments.bysquare.version' => '1.1.0']);
    $document = new PayBySquare([bsqOrder()]);

    expect(Qr::payBySquare($document)->payload()->toQrString())->toStartWith('04')
        ->and($document->encode())->toStartWith('08');
});

it('refuses a beneficiary name that deburring would blank', function (string $name, BySquareVersion $version): void {
    try {
        (new PayBySquare([bsqOrder(new Beneficiary($name))], version: $version))->encode();
        $this->fail('expected a failure');
    } catch (InvalidPayloadException $e) {
        expect($e->field)->toBe('beneficiary.name')
            ->and($e->reason)->toBe(InvalidPayloadException::REASON_UNREPRESENTABLE);
    }

    $kept = new PayBySquare([bsqOrder(new Beneficiary($name))], version: $version, deburr: false);

    expect(PayBySquare::decode($kept->encode())->payments[0]->beneficiary?->name)->toBe($name);
})->with([
    '1.2.0, blanks to nothing' => ['李明', BySquareVersion::V1_2_0],
    '1.1.0, blanks to a space' => ['李明 商店', BySquareVersion::V1_1_0],
    '1.2.0, blanks to a space' => ['李明 商店', BySquareVersion::V1_2_0],
]);

it('trims deburred beneficiary and note text so the output decodes', function (): void {
    $document = new PayBySquare([bsqOrder(new Beneficiary('Ján Novák 李明', '李明 商店', 'Košice 商店'), extra: ['note' => '商店 Dar'])]);

    expect($document->serialize())->toContain("Jan Novak\t\tKosice")->toContain("\tDar\t")
        ->and(PayBySquare::decode($document->encode())->serialize())->toBe($document->serialize());
});

it('decodes a blank beneficiary name written by older encoders as no beneficiary', function (): void {
    $serialized = str_replace("\tJana\t\t", "\t \t\t", (new PayBySquare([bsqOrder()], version: BySquareVersion::V1_1_0))->serialize());

    expect(BySquareSerializer::unserialize($serialized, BySquareVersion::V1_1_0)->payments[0]->beneficiary)->toBeNull();
});

it('rejects text that is not valid UTF-8 instead of emitting a code it cannot decode', function (Closure $build, string $field): void {
    try {
        $build();
        $this->fail('expected a failure');
    } catch (InvalidPayloadException $e) {
        expect($e->field)->toBe($field)->and($e->reason)->toBe(InvalidPayloadException::REASON_INVALID_FORMAT);
    }
})->with([
    'invoice id' => [fn () => new PayBySquare([bsqOrder()], "Fakt\xFAra"), 'invoiceId'],
    'originator\'s reference' => [fn () => bsqOrder(extra: ['originatorsReference' => "R\xE1"]), 'originatorsReference'],
    'note' => [fn () => bsqOrder(extra: ['note' => "Dar\xE1"]), 'note'],
    'beneficiary name' => [fn () => new Beneficiary("J\xE1n"), 'beneficiary.name'],
    'beneficiary street' => [fn () => new Beneficiary('Jan', "Hlavn\xE1"), 'beneficiary.street'],
    'beneficiary city' => [fn () => new Beneficiary('Jan', null, "Ko\xE1ice"), 'beneficiary.city'],
    'direct debit reference' => [fn () => new DirectDebitDetails(originatorsReference: "R\xE1"), 'directDebit.originatorsReference'],
    'mandate id' => [fn () => new DirectDebitDetails(mandateId: "M\xE1"), 'directDebit.mandateId'],
    'creditor id' => [fn () => new DirectDebitDetails(creditorId: "C\xE1"), 'directDebit.creditorId'],
    'contract id' => [fn () => new DirectDebitDetails(contractId: "K\xE1"), 'directDebit.contractId'],
]);

it('caps payments and accounts at the 99 the data model can count', function (): void {
    $accounts = static fn (int $count): array => array_fill(0, $count, new BankAccount(SK_IBAN));

    foreach ([
        'payments' => fn () => new PayBySquare(array_fill(0, 100, bsqOrder())),
        'accounts' => fn () => Payment::order(Money::ofMinor(100, 'EUR'), $accounts(100)),
    ] as $field => $build) {
        try {
            $build();
            $this->fail("expected 100 {$field} to fail");
        } catch (InvalidPayloadException $e) {
            expect($e->field)->toBe($field)->and($e->reason)->toBe(InvalidPayloadException::REASON_OUT_OF_RANGE);
        }
    }

    $payments = new PayBySquare(array_fill(0, 99, bsqOrder()));
    $accountsDocument = new PayBySquare([Payment::order(Money::ofMinor(100, 'EUR'), $accounts(99), new Beneficiary('Jana'))]);

    expect(PayBySquare::decode($payments->encode())->payments)->toHaveCount(99)
        ->and(PayBySquare::decode($accountsDocument->encode())->payments[0]->accounts)->toHaveCount(99);
});

it('rejects a symbol with a trailing newline', function (Closure $build, string $field): void {
    try {
        $build();
        $this->fail('expected a failure');
    } catch (InvalidPayloadException $e) {
        expect($e->field)->toBe($field)->and($e->reason)->toBe(InvalidPayloadException::REASON_INVALID_FORMAT);
    }

    expect(bsqOrder(extra: ['variableSymbol' => '1234567890', 'constantSymbol' => '0308', 'specificSymbol' => '123'])->constantSymbol)->toBe('0308');
})->with([
    'variable symbol' => [fn () => bsqOrder(extra: ['variableSymbol' => "123\n"]), 'variableSymbol'],
    'variable symbol at the limit' => [fn () => bsqOrder(extra: ['variableSymbol' => "1234567890\n"]), 'variableSymbol'],
    'constant symbol' => [fn () => bsqOrder(extra: ['constantSymbol' => "0308\n"]), 'constantSymbol'],
    'specific symbol' => [fn () => bsqOrder(extra: ['specificSymbol' => "123\n"]), 'specificSymbol'],
    'direct debit variable symbol' => [fn () => new DirectDebitDetails(variableSymbol: "123\n"), 'directDebit.variableSymbol'],
    'direct debit specific symbol' => [fn () => new DirectDebitDetails(specificSymbol: "123\n"), 'directDebit.specificSymbol'],
]);
