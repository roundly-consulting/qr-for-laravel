<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Enums\WifiSecurity;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;
use RoundlyConsulting\Qr\Facades\Qr;
use RoundlyConsulting\Qr\Payloads\Text;

it('treats otpauth and Wi-Fi strings as secrets through every entry point', function (Closure $sensitivity): void {
    expect($sensitivity())->toBe(Sensitivity::Secret);
})->with([
    'text' => [fn () => Qr::text('otpauth://totp/A:b?secret=JBSWY3DP')->svg()->sensitivity()],
    'make' => [fn () => Qr::make(' WIFI:T:WPA;S:x;P:12345678;;')->svg()->sensitivity()],
    'svg shortcut' => [fn () => Qr::svg('otpauth-migration://offline?data=abc')->sensitivity()],
    'payload' => [fn () => Qr::make(new Text('otpauth://hotp/x?secret=AB'))->svg()->sensitivity()],
]);

it('locks 2FA seeds at Secret', function (Closure $build): void {
    expect($build)->toThrow(InvalidOptionException::class, '[sensitivity]');
})->with([
    fn () => Qr::otpauth('otpauth://totp/A:b?secret=JBSWY3DP')->sensitivity(Sensitivity::Public)->svg(),
    fn () => Qr::text('otpauth://totp/A:b?secret=JBSWY3DP')->sensitivity(Sensitivity::Personal)->matrix(),
]);

it('allows restating Secret on a locked seed', function (): void {
    expect(Qr::otpauth('otpauth://totp/A:b?secret=JBSWY3DP')->sensitivity(Sensitivity::Secret)->svg()->sensitivity())->toBe(Sensitivity::Secret);
});

it('lets hosts raise or lower unlocked sensitivities', function (): void {
    expect(Qr::wifi('Guest', 'guest-password', WifiSecurity::Wpa)->sensitivity(Sensitivity::Public)->svg()->sensitivity())->toBe(Sensitivity::Public)
        ->and(Qr::email('a@b.co')->sensitivity(Sensitivity::Public)->svg()->sensitivity())->toBe(Sensitivity::Public)
        ->and(Qr::text('public')->sensitivity(Sensitivity::Secret)->svg()->sensitivity())->toBe(Sensitivity::Secret);
});
