<?php

declare(strict_types=1);

use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Qr\Contracts\QrFactory;
use RoundlyConsulting\Qr\DataTransferObjects\QrOptions;
use RoundlyConsulting\Qr\Encoder\Encoder;
use RoundlyConsulting\Qr\Enums\EciMode;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\Segmentation;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Enums\SmsFormat;
use RoundlyConsulting\Qr\Enums\WifiSecurity;
use RoundlyConsulting\Qr\Exceptions\DataTooLongException;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;
use RoundlyConsulting\Qr\Facades\Qr;
use RoundlyConsulting\Qr\Payloads\Email;
use RoundlyConsulting\Qr\Payloads\Geo;
use RoundlyConsulting\Qr\Payloads\Otpauth;
use RoundlyConsulting\Qr\Payloads\Payments\Epc\EpcPayment;
use RoundlyConsulting\Qr\Payloads\Phone;
use RoundlyConsulting\Qr\Payloads\Sms;
use RoundlyConsulting\Qr\Payloads\Text;
use RoundlyConsulting\Qr\Payloads\Url;
use RoundlyConsulting\Qr\Payloads\VCard;
use RoundlyConsulting\Qr\Payloads\Wifi;
use RoundlyConsulting\Qr\PendingQr;
use RoundlyConsulting\Qr\QrManager;
use RoundlyConsulting\Qr\Testing\MatrixDecoder;
use RoundlyConsulting\Qr\ValueObjects\EncodingInfo;

/*
 * The facade contract. `toReachEveryAction()` is omitted: qr has no `src/Actions` — encoding
 * is stateless computation, which the convention serves with a plain manager. `toBeFakeable()`
 * is omitted too: encoding is pure and deterministic, so tests assert on the real output
 * (`Testing\MatrixDecoder`) instead of a recording fake.
 */
it('documents its root', function (): void {
    expect(Qr::class)->toDocumentItsRoot();
});

// `wifi($password)` and `otpauth($uriOrPayload)`: the facade frame must not hold either secret.
it('redacts sensitive arguments in its own frame', function (): void {
    expect(Qr::class)->toRedactSensitiveArguments(methods: 2);
});

it('resolves the manager as a singleton behind the contract', function (): void {
    expect(app(QrFactory::class))->toBeInstanceOf(QrManager::class)
        ->and(app(QrManager::class))->toBe(app(QrFactory::class))
        ->and(Qr::getFacadeRoot())->toBe(app(QrFactory::class));
});

it('builds every payload through the facade', function (PendingQr $pending, string $payload, string $content): void {
    expect($pending->payload())->toBeInstanceOf($payload)
        ->and(MatrixDecoder::decode($pending->matrix())->bytes)->toBe($content);
})->with([
    'make string' => [fn () => Qr::make('hi'), Text::class, 'hi'],
    'make payload' => [fn () => Qr::make(new Url('https://a.io')), Url::class, 'https://a.io'],
    'text' => [fn () => Qr::text('hello'), Text::class, 'hello'],
    'url' => [fn () => Qr::url('https://example.com'), Url::class, 'https://example.com'],
    'email' => [fn () => Qr::email('a@b.co', 'Hi'), Email::class, 'mailto:a@b.co?subject=Hi'],
    'phone' => [fn () => Qr::phone('+421 900 111 222'), Phone::class, 'tel:+421900111222'],
    'sms' => [fn () => Qr::sms('0900111222', 'x', SmsFormat::Uri), Sms::class, 'sms:0900111222?body=x'],
    'wifi' => [fn () => Qr::wifi('Guest', null, WifiSecurity::None), Wifi::class, 'WIFI:T:nopass;S:Guest;;'],
    'vcard' => [fn () => Qr::vcard(new VCard('Jana')), VCard::class, "BEGIN:VCARD\r\nVERSION:3.0\r\nN:;Jana;;;\r\nFN:Jana\r\nEND:VCARD"],
    'geo' => [fn () => Qr::geo(48.1486, 17.1077), Geo::class, 'geo:48.1486,17.1077'],
    'otpauth string' => [fn () => Qr::otpauth('otpauth://totp/A:b?secret=JBSWY3DP'), Otpauth::class, 'otpauth://totp/A:b?secret=JBSWY3DP'],
    'otpauth payload' => [fn () => Qr::otpauth(Otpauth::totp('JBSWY3DP', 'b', 'A')), Otpauth::class, 'otpauth://totp/A:b?secret=JBSWY3DP&issuer=A&algorithm=SHA1&digits=6&period=30'],
]);

it('offers one-call svg and matrix shortcuts', function (): void {
    $svg = Qr::svg('hello', new QrOptions(size: 120, errorCorrection: ErrorCorrection::High, margin: 1, title: 'Hi'));

    expect($svg->width())->toBe(120)
        ->and($svg->viewBoxSize())->toBe(23)
        ->and($svg->toString())->toContain('<title>Hi</title>')
        ->and(Qr::svg('hello')->width())->toBe(256)
        ->and(Qr::matrix('hello', new QrOptions(mask: 3))->mask())->toBe(3)
        ->and(Qr::matrix('hello')->version())->toBe(1);
});

it('renders a Stringable, Htmlable builder', function (): void {
    $pending = Qr::text('hello');

    expect((string) $pending)->toStartWith('<svg ')
        ->and($pending->toHtml())->toBe((string) $pending)
        ->and($pending->toDataUri())->toStartWith('data:image/svg+xml;charset=utf-8,')
        ->and(Qr::text('hello')->xmlDeclaration()->svg()->toString())->toStartWith('<?xml');
});

it('does not resolve anything eagerly at boot', function (): void {
    // otpauth rendering works with only this provider registered (no money, cache or DB).
    expect(Qr::otpauth('otpauth://totp/A:b?secret=JBSWY3DP')->svg()->sensitivity())->toBe(Sensitivity::Secret);
});

function facadeEpc(): EpcPayment
{
    return new EpcPayment(
        name: 'Franz Mustermänn',
        iban: 'DE71110220330123456789',
        bic: 'BHBLDEHHXXX',
        amount: Money::ofMinor(1230, 'EUR'),
        purpose: 'GDDS',
        reference: 'RF18539007547034',
    );
}

it('serves fits and info to an injected contract', function (): void {
    $qr = app(QrFactory::class);

    expect($qr)->toBe(Qr::getFacadeRoot())
        ->and($qr->fits('hello'))->toBeTrue()
        ->and($qr->info('hello'))->toBeInstanceOf(EncodingInfo::class)
        ->and($qr->info('hello')->version)->toBe(1);
});

it('describes an encoding in one call', function (): void {
    $info = Qr::info('hello');

    expect($info)->toBeInstanceOf(EncodingInfo::class)
        ->and($info->version)->toBe(1)
        ->and($info->errorCorrection)->toBe(ErrorCorrection::High) // boosted from M: room left in v1
        ->and($info->errorCorrectionBoosted)->toBeTrue()
        ->and($info->maskPenalties)->toHaveCount(8);

    $forced = Qr::info('hello', new QrOptions(errorCorrection: ErrorCorrection::High, minVersion: 3, mask: 3));

    expect($forced->version)->toBe(3)
        ->and($forced->errorCorrection)->toBe(ErrorCorrection::High)
        ->and($forced->mask)->toBe(3)
        ->and($forced->maskPenalties)->toBe([])
        ->and($forced)->toEqual(Qr::make('hello')->withOptions(new QrOptions(errorCorrection: ErrorCorrection::High, minVersion: 3, mask: 3))->info());
});

it('describes a payment payload under its requirements', function (): void {
    $info = Qr::info(facadeEpc());

    expect($info->version)->toBe(6)
        ->and($info->errorCorrection)->toBe(ErrorCorrection::Medium)
        ->and($info->errorCorrectionBoosted)->toBeFalse()
        ->and($info->eciDesignator)->toBeNull();
});

it('checks whether data fits', function (): void {
    // 3391 alphanumerics is the version 40-M ceiling.
    expect(Qr::fits('hello'))->toBeTrue()
        ->and(Qr::fits(str_repeat('A', 3391)))->toBeTrue()
        ->and(Qr::fits(str_repeat('A', 3392)))->toBeFalse()
        ->and(Qr::fits(str_repeat('1', Encoder::MAX_INPUT_BYTES + 1)))->toBeFalse();
});

it('checks a fit at an explicit level and version ceiling', function (): void {
    expect(Qr::fits(str_repeat('A', 3391), ErrorCorrection::High))->toBeFalse()
        ->and(Qr::fits(str_repeat('a', 331), ErrorCorrection::Medium, 13))->toBeTrue()
        ->and(Qr::fits(str_repeat('a', 332), ErrorCorrection::Medium, 13))->toBeFalse()
        ->and(Qr::fits(str_repeat('a', 14), maxVersion: 1))->toBeTrue()
        ->and(Qr::fits(str_repeat('a', 15), maxVersion: 1))->toBeFalse();
});

it('checks a fit with an explicit segmentation', function (): void {
    // 34 digits fill version 1-M in numeric mode; as bytes they need version 3.
    $digits = str_repeat('7', 34);

    expect(Qr::fits($digits, maxVersion: 1))->toBeTrue()
        ->and(Qr::fits($digits, maxVersion: 1, segmentation: Segmentation::Byte))->toBeFalse()
        ->and(Qr::fits($digits, maxVersion: 3, segmentation: Segmentation::Byte))->toBeTrue();
});

it('checks a fit under the configured settings', function (): void {
    config(['qr.error_correction' => 'H']);
    expect(Qr::fits(str_repeat('A', 3391)))->toBeFalse();

    config(['qr.error_correction' => 'M', 'qr.versions.max' => 10]);
    expect(Qr::fits(str_repeat('a', 214)))->toBeFalse()
        ->and(Qr::fits(str_repeat('a', 214), maxVersion: 40))->toBeTrue();

    // A configured minimum above the requested ceiling opens the window instead of throwing.
    config(['qr.versions.min' => 20, 'qr.versions.max' => 40]);
    expect(Qr::fits('hello', maxVersion: 5))->toBeTrue();
});

it('checks a payload fit under the payload requirements', function (): void {
    expect(Qr::fits(facadeEpc()))->toBeTrue()                    // version 6
        ->and(Qr::fits(facadeEpc(), maxVersion: 5))->toBeFalse()
        ->and(Qr::fits(facadeEpc(), ErrorCorrection::Medium))->toBeTrue(); // the locked level itself is fine
});

it('refuses a fit check that overrides a locked option or leaves the version range', function (): void {
    expect(fn () => Qr::fits(facadeEpc(), ErrorCorrection::High))->toThrow(InvalidOptionException::class)
        ->and(fn () => Qr::fits(facadeEpc(), segmentation: Segmentation::Optimal))->toThrow(InvalidOptionException::class)
        ->and(fn () => Qr::fits('hello', maxVersion: 41))->toThrow(InvalidOptionException::class)
        ->and(fn () => Qr::fits('hello', maxVersion: 0))->toThrow(InvalidOptionException::class);
});

it('checks a fit fluently on the builder', function (): void {
    $long = str_repeat('a', 15);

    expect(Qr::text($long)->fits())->toBeTrue()
        ->and(Qr::text($long)->version(1)->fits())->toBeFalse()
        ->and(Qr::text($long)->version(2)->fits())->toBeTrue()
        ->and(Qr::url('https://example.com')->errorCorrection('H')->versions(1, 2)->fits())->toBeFalse()
        ->and(Qr::url('https://example.com')->errorCorrection('H')->versions(1, 3)->fits())->toBeTrue();
});

it('agrees with the encoder on every boundary', function (ErrorCorrection $level, EciMode $eci, int $maxVersion): void {
    config(['qr.eci' => $eci->value]);

    foreach ([1, 2, 7, 14, 15, 16, 32, 33, 100, 213, 214, 331, 332, 2331, 2332] as $length) {
        $data = str_repeat('é', intdiv($length, 2)).str_repeat('x', $length % 2);
        $pending = Qr::text($data)->errorCorrection($level)->versions(1, $maxVersion);

        try {
            $pending->matrix();
            $encodes = true;
        } catch (DataTooLongException) {
            $encodes = false;
        }

        expect(Qr::fits($data, $level, $maxVersion))->toBe($encodes, "length {$length}")
            ->and($pending->fits())->toBe($encodes, "length {$length}");
    }
})->with([
    'L, auto ECI, v10' => [ErrorCorrection::Low, EciMode::Auto, 10],
    'M, always ECI, v13' => [ErrorCorrection::Medium, EciMode::Always, 13],
    'H, never ECI, v40' => [ErrorCorrection::High, EciMode::Never, 40],
]);
