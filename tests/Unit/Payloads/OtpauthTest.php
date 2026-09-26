<?php

declare(strict_types=1);

use RoundlyConsulting\Crypto\Otp\OtpAlgorithm;
use RoundlyConsulting\Crypto\Otp\ProvisioningUri;
use RoundlyConsulting\Qr\Enums\OtpType;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;
use RoundlyConsulting\Qr\Facades\Qr;
use RoundlyConsulting\Qr\Payloads\Otpauth;
use RoundlyConsulting\Qr\Testing\MatrixDecoder;

const OTP_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

it('builds TOTP URIs byte-identical to the shared provisioning builder', function (OtpAlgorithm $algorithm, int $digits, int $period): void {
    $expected = ProvisioningUri::totp(OTP_SECRET, 'user@acme.io', 'Acme: Admin', $algorithm, $digits, $period);
    $payload = Otpauth::totp(OTP_SECRET, 'user@acme.io', 'Acme: Admin', $algorithm, $digits, $period);

    expect($payload->toQrString())->toBe($expected)
        ->and(Otpauth::fromUri($expected)->toQrString())->toBe($expected)
        ->and(MatrixDecoder::decode(Qr::otpauth($payload)->matrix())->bytes)->toBe($expected);
})->with(OtpAlgorithm::cases())->with([6, 7, 8])->with([15, 30, 60, 120]);

it('parses issuers containing colons, spaces and non-ASCII characters', function (string $issuer, string $account): void {
    $uri = ProvisioningUri::totp(OTP_SECRET, $account, $issuer);
    $payload = Otpauth::fromUri($uri);

    expect($payload->issuer())->toBe($issuer)
        ->and($payload->account())->toBe($account)
        ->and($payload->type())->toBe(OtpType::Totp);
})->with([
    ['Acme: Admin', 'user@acme.io'],
    ['Veterinárna klinika', 'jana.novakova@example.sk'],
    ['A:B:C', 'plain'],
]);

it('parses the minimal URI shape a fake two-factor service emits', function (): void {
    $payload = Otpauth::fromUri('otpauth://totp/Fake:label?secret=FAKESECRET234567&issuer=Fake');

    expect($payload->issuer())->toBe('Fake')->and($payload->account())->toBe('label');
});

it('reads the issuer from the query when the label has none', function (): void {
    expect(Otpauth::fromUri('otpauth://totp/alice?secret=JBSWY3DP&issuer=Acme')->issuer())->toBe('Acme')
        ->and(Otpauth::fromUri('otpauth://totp/alice?secret=JBSWY3DP')->issuer())->toBe('')
        ->and(Otpauth::fromUri('otpauth://totp/Acme%3Aalice?secret=jbswy3dp')->issuer())->toBe('Acme')
        ->and(Otpauth::fromUri('OTPAUTH://TOTP/Acme:alice?Secret=JBSWY3DP&&digits=8&period=60&algorithm=sha512')->account())->toBe('alice');
});

it('builds and parses HOTP URIs', function (): void {
    $payload = Otpauth::hotp(OTP_SECRET, 'bob', 'Acme', 7, OtpAlgorithm::Sha256, 8);

    expect($payload->toQrString())->toBe('otpauth://hotp/Acme:bob?secret='.OTP_SECRET.'&issuer=Acme&algorithm=SHA256&digits=8&counter=7')
        ->and($payload->type())->toBe(OtpType::Hotp)
        ->and(Otpauth::fromUri($payload->toQrString())->type())->toBe(OtpType::Hotp);
});

it('is always secret, locked and described generically', function (): void {
    $payload = Otpauth::totp(OTP_SECRET, 'user', 'Acme');

    expect($payload->sensitivity())->toBe(Sensitivity::Secret)
        ->and($payload->requirements()->locks('sensitivity'))->toBeTrue()
        ->and($payload->description(app('translator')))->toBe('QR code to set up two-factor authentication');
});

it('rejects invalid URIs without echoing any part of them', function (string $uri, string $field): void {
    try {
        Otpauth::fromUri($uri);
    } catch (InvalidPayloadException $e) {
        expect($e->field)->toBe($field)
            ->and($e->getMessage())->not->toContain('JBSWY3DP')
            ->and($e->getMessage())->not->toContain('alice');

        return;
    }

    $this->fail('expected a failure');
})->with([
    ['https://example.com/?secret=JBSWY3DP', 'uri'],
    ['otpauth://motp/alice?secret=JBSWY3DP', 'type'],
    ['otpauth://totp/?secret=JBSWY3DP', 'account'],
    ['otpauth://totp/Acme:?secret=JBSWY3DP', 'account'],
    ['otpauth://totp/alice', 'secret'],
    ['otpauth://totp/alice?secret=JBSWY3DP1', 'secret'],
    ['otpauth://totp/alice?secret=JBSWY3DP&algorithm=MD5', 'algorithm'],
    ['otpauth://totp/alice?secret=JBSWY3DP&digits=5', 'digits'],
    ['otpauth://totp/alice?secret=JBSWY3DP&digits=11', 'digits'],
    ['otpauth://totp/alice?secret=JBSWY3DP&period=0', 'period'],
    ['otpauth://totp/alice?secret=JBSWY3DP&period=x', 'period'],
    ['otpauth://hotp/alice?secret=JBSWY3DP', 'counter'],
    ['otpauth://hotp/alice?secret=JBSWY3DP&counter=-1', 'counter'],
    ['otpauth://totp/alice?secret=JBSWY3DP&x='.str_repeat('a', 1024), 'uri'],
]);

it('rejects invalid build parameters', function (Closure $build, string $field): void {
    expect($build)->toThrow(InvalidPayloadException::class, "[{$field}]");
})->with([
    [fn () => Otpauth::totp('', 'user', 'Acme'), 'secret'],
    [fn () => Otpauth::totp('not base32!', 'user', 'Acme'), 'secret'],
    [fn () => Otpauth::totp(OTP_SECRET, '', 'Acme'), 'account'],
    [fn () => Otpauth::totp(OTP_SECRET, 'user', ''), 'issuer'],
    [fn () => Otpauth::totp(OTP_SECRET, 'user', 'Acme', digits: 5), 'digits'],
    [fn () => Otpauth::totp(OTP_SECRET, 'user', 'Acme', period: 0), 'period'],
    [fn () => Otpauth::hotp(OTP_SECRET, 'user', 'Acme', -1), 'counter'],
]);
