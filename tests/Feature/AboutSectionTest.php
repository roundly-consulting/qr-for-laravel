<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

function qrAbout(): string
{
    Artisan::call('about', ['--only' => 'qr']);

    return Artisan::output();
}

it('renders the configuration without leaking the cache store name', function (): void {
    config(['qr.cache.store' => 'qr-secret-store-name', 'qr.cache.enabled' => true]);

    expect('qr')->toLeakNoSecrets(
        secrets: ['qr-secret-store-name'],
        mustRender: ['M, boost ON', '1–40', '<x-qr-code>', 'v002, utf-8', '1.2.0, deburr ON', 'ON (custom store) 86400s'],
    );
});

it('reports the defaults', function (): void {
    expect(qrAbout())
        ->toContain('256px, margin 4, square/square')
        ->toContain('64 entries')
        ->toContain('auto');
});

it('reports disabled and alternative settings', function (): void {
    config([
        'qr.svg.size' => null,
        'qr.memo.entries' => 0,
        'qr.blade.component' => null,
        'qr.cache.enabled' => false,
        'qr.boost_error_correction' => false,
        'qr.payments.bysquare.deburr' => 'false',
        'qr.mask' => 3,
    ]);

    expect(qrAbout())
        ->toContain('responsive, margin 4')
        ->toContain('Memo')
        ->toContain('M, boost OFF')
        ->toContain('1.2.0, deburr OFF');
});

it('reports the default cache store as such', function (): void {
    config(['qr.cache.enabled' => true, 'qr.cache.store' => null]);

    expect(qrAbout())->toContain('ON (default store) 86400s');
});

it('still renders on a misconfigured host', function (): void {
    config(['qr.error_correction' => ['nonsense'], 'qr.versions.min' => [], 'qr.svg.margin' => true]);

    expect(qrAbout())->toContain('M, boost ON')->toContain('1–40')->toContain('margin 4');
});

it('reports blank settings as their defaults, the way the readers see them', function (): void {
    config([
        'qr.error_correction' => '',
        'qr.boost_error_correction' => '',
        'qr.mask' => ' ',
        'qr.eci' => '',
        'qr.svg.size' => '',
        'qr.svg.margin' => '',
        'qr.cache.enabled' => '',
        'qr.blade.component' => '  ',
        'qr.payments.bysquare.deburr' => '',
    ]);

    expect(qrAbout())
        ->toContain('M, boost ON')
        ->toContain('responsive, margin 4')
        ->toContain('1.2.0, deburr ON')
        ->toMatch('/Mask\s*\.*\s*auto/')
        ->toMatch('/ECI\s*\.*\s*auto/')
        ->toMatch('/Cache\s*\.*\s*OFF/')
        ->toMatch('/Blade component\s*\.*\s*OFF/');

    config(['qr.cache.enabled' => true, 'qr.cache.store' => ' ']);

    expect(qrAbout())->toContain('ON (default store) 86400s');
});
