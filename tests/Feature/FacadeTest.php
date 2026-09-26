<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Contracts\QrFactory;
use RoundlyConsulting\Qr\DataTransferObjects\QrOptions;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Enums\SmsFormat;
use RoundlyConsulting\Qr\Enums\WifiSecurity;
use RoundlyConsulting\Qr\Facades\Qr;
use RoundlyConsulting\Qr\Payloads\Email;
use RoundlyConsulting\Qr\Payloads\Geo;
use RoundlyConsulting\Qr\Payloads\Otpauth;
use RoundlyConsulting\Qr\Payloads\Phone;
use RoundlyConsulting\Qr\Payloads\Sms;
use RoundlyConsulting\Qr\Payloads\Text;
use RoundlyConsulting\Qr\Payloads\Url;
use RoundlyConsulting\Qr\Payloads\VCard;
use RoundlyConsulting\Qr\Payloads\Wifi;
use RoundlyConsulting\Qr\PendingQr;
use RoundlyConsulting\Qr\QrManager;
use RoundlyConsulting\Qr\Testing\MatrixDecoder;

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
