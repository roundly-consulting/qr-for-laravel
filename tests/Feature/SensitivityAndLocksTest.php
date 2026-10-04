<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use RoundlyConsulting\Qr\Enums\EciMode;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Enums\WifiSecurity;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;
use RoundlyConsulting\Qr\Facades\Qr;
use RoundlyConsulting\Qr\Payloads\Payments\Epc\EpcPayment;
use RoundlyConsulting\Qr\Payloads\Text;
use RoundlyConsulting\Qr\Support\MatrixMemo;

it('treats otpauth and Wi-Fi strings as secrets through every entry point', function (Closure $sensitivity): void {
    expect($sensitivity())->toBe(Sensitivity::Secret);
})->with([
    'text' => [fn () => Qr::text('otpauth://totp/A:b?secret=JBSWY3DP')->svg()->sensitivity()],
    'make' => [fn () => Qr::make(' WIFI:T:WPA;S:x;P:12345678;;')->svg()->sensitivity()],
    'svg shortcut' => [fn () => Qr::svg('otpauth-migration://offline?data=abc')->sensitivity()],
    'payload' => [fn () => Qr::make(new Text('otpauth://hotp/x?secret=AB'))->svg()->sensitivity()],
    'url otpauth' => [fn () => Qr::url('otpauth://totp/A:b?secret=JBSWY3DP', ['otpauth'])->svg()->sensitivity()],
    'url otpauth-migration' => [fn () => Qr::url('otpauth-migration://offline?data=abc', ['otpauth-migration'])->svg()->sensitivity()],
    'url wifi' => [fn () => Qr::url('WIFI:T:WPA;S:x;P:12345678;;', ['wifi'])->svg()->sensitivity()],
]);

it('locks 2FA seeds at Secret', function (Closure $build): void {
    expect($build)->toThrow(InvalidOptionException::class, '[sensitivity]');
})->with([
    fn () => Qr::otpauth('otpauth://totp/A:b?secret=JBSWY3DP')->sensitivity(Sensitivity::Public)->svg(),
    fn () => Qr::text('otpauth://totp/A:b?secret=JBSWY3DP')->sensitivity(Sensitivity::Personal)->matrix(),
    fn () => Qr::url('otpauth://totp/A:b?secret=JBSWY3DP', ['otpauth'])->sensitivity(Sensitivity::Public)->svg(),
]);

it('allows restating Secret on a locked seed', function (): void {
    expect(Qr::otpauth('otpauth://totp/A:b?secret=JBSWY3DP')->sensitivity(Sensitivity::Secret)->svg()->sensitivity())->toBe(Sensitivity::Secret);
});

it('lets hosts raise or lower unlocked sensitivities', function (): void {
    expect(Qr::wifi('Guest', 'guest-password', WifiSecurity::Wpa)->sensitivity(Sensitivity::Public)->svg()->sensitivity())->toBe(Sensitivity::Public)
        ->and(Qr::email('a@b.co')->sensitivity(Sensitivity::Public)->svg()->sensitivity())->toBe(Sensitivity::Public)
        ->and(Qr::text('public')->sensitivity(Sensitivity::Secret)->svg()->sensitivity())->toBe(Sensitivity::Secret);
});

it('keeps a 2FA seed sent through Qr::url out of the memo, the SVG cache and shared HTTP caches', function (): void {
    config(['cache.default' => 'array', 'qr.cache.enabled' => true, 'qr.cache.store' => 'array']);
    $memo = app(MatrixMemo::class);
    $memo->flush();
    Route::get('/qr/seed', fn () => Qr::url('otpauth://totp/Acme:u?secret=JBSWY3DPEHPK3PXP&issuer=Acme', ['otpauth']));

    $response = $this->get('/qr/seed')->assertOk()->assertHeaderMissing('ETag');
    Qr::url('WIFI:T:WPA;S:x;P:12345678;;', ['wifi'])->svg();

    $store = Cache::store('array')->getStore();

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($memo->count())->toBe(0)
        ->and((new ReflectionProperty($store, 'storage'))->getValue($store))->toBe([]);
});

it('explains a locked option in words that fit every content type', function (string $locale, Closure $build, string $message): void {
    app()->setLocale($locale);

    try {
        $build();
    } catch (InvalidOptionException $exception) {
        expect($exception->reason)->toBe('locked_by_payload')
            ->and(__('qr::qr.errors.'.$exception->reason, ['field' => $exception->field]))->toBe($message);

        return;
    }

    test()->fail('The locked option was not refused.');
})->with([
    'en, otpauth' => ['en', fn () => Qr::otpauth('otpauth://totp/A:b?secret=JBSWY3DP')->sensitivity(Sensitivity::Public)->svg(), 'The sensitivity option is fixed by this content type.'],
    'en, otpauth text' => ['en', fn () => Qr::text('otpauth://totp/A:b?secret=JBSWY3DP')->sensitivity(Sensitivity::Personal)->matrix(), 'The sensitivity option is fixed by this content type.'],
    'en, payment' => ['en', fn () => Qr::epc(new EpcPayment('Jana', 'SK9611000000002918599669', 'TATRSKBX'))->eci(EciMode::Always)->matrix(), 'The eci option is fixed by this content type.'],
    'sk, otpauth' => ['sk', fn () => Qr::otpauth('otpauth://totp/A:b?secret=JBSWY3DP')->sensitivity(Sensitivity::Public)->svg(), 'Možnosť sensitivity je pevne určená týmto typom obsahu.'],
    'sk, otpauth url' => ['sk', fn () => Qr::url('otpauth://totp/A:b?secret=JBSWY3DP', ['otpauth'])->sensitivity(Sensitivity::Public)->svg(), 'Možnosť sensitivity je pevne určená týmto typom obsahu.'],
]);
