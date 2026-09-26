<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use RoundlyConsulting\Qr\Facades\Qr;

it('returns QR codes straight from a route', function (): void {
    Route::get('/qr/public', fn () => Qr::url('https://example.com'));
    Route::get('/qr/personal', fn () => Qr::email('a@b.co'));
    Route::get('/qr/secret', fn () => Qr::otpauth('otpauth://totp/A:b?secret=JBSWY3DP'));

    $public = $this->get('/qr/public');
    $public->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($public->headers->get('Cache-Control'))->toContain('public')
        ->and($public->getContent())->toStartWith('<?xml version="1.0" encoding="UTF-8"?><svg');

    $this->get('/qr/public', ['If-None-Match' => (string) $public->headers->get('ETag')])->assertStatus(304);

    expect($this->get('/qr/personal')->headers->get('Cache-Control'))->toContain('private')
        ->and($this->get('/qr/secret')->headers->get('Cache-Control'))->toContain('no-store');

    $this->get('/qr/secret')->assertHeaderMissing('ETag')->assertHeader('Pragma', 'no-cache');
});
