<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Enums\BySquare\BySquareVersion;
use RoundlyConsulting\Qr\Enums\EciMode;
use RoundlyConsulting\Qr\Enums\EpcCharset;
use RoundlyConsulting\Qr\Enums\EpcVersion;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\FinderStyle;
use RoundlyConsulting\Qr\Enums\ModuleStyle;
use RoundlyConsulting\Qr\Exceptions\InvalidQrConfigException;
use RoundlyConsulting\Qr\Support\ConfigGuard;

it('reads every shipped default', function (): void {
    expect(ConfigGuard::errorCorrection())->toBe(ErrorCorrection::Medium)
        ->and(ConfigGuard::boostErrorCorrection())->toBeTrue()
        ->and(ConfigGuard::minVersion())->toBe(1)
        ->and(ConfigGuard::maxVersion())->toBe(40)
        ->and(ConfigGuard::mask())->toBeNull()
        ->and(ConfigGuard::eci())->toBe(EciMode::Auto)
        ->and(ConfigGuard::kanji())->toBeFalse()
        ->and(ConfigGuard::svgSize())->toBe(256)
        ->and(ConfigGuard::svgMargin())->toBe(4)
        ->and(ConfigGuard::svgForeground()->toSvg())->toBe('#000000')
        ->and(ConfigGuard::svgBackground()->toSvg())->toBe('#ffffff')
        ->and(ConfigGuard::moduleStyle())->toBe(ModuleStyle::Square)
        ->and(ConfigGuard::moduleRadius())->toBe(0.5)
        ->and(ConfigGuard::finderStyle())->toBe(FinderStyle::Square)
        ->and(ConfigGuard::finderColor())->toBeNull()
        ->and(ConfigGuard::xmlDeclaration())->toBeFalse()
        ->and(ConfigGuard::responseMaxAge())->toBe(86400)
        ->and(ConfigGuard::responseImmutable())->toBeFalse()
        ->and(ConfigGuard::memoEntries())->toBe(64)
        ->and(ConfigGuard::cacheEnabled())->toBeFalse()
        ->and(ConfigGuard::cacheStore())->toBeNull()
        ->and(ConfigGuard::cacheTtl())->toBe(86400)
        ->and(ConfigGuard::cachePrefix())->toBe('qr')
        ->and(ConfigGuard::bladeComponent())->toBe('qr-code')
        ->and(ConfigGuard::epcVersion())->toBe(EpcVersion::V002)
        ->and(ConfigGuard::epcCharset())->toBe(EpcCharset::Utf8)
        ->and(ConfigGuard::epcStrictCharset())->toBeFalse()
        ->and(ConfigGuard::bySquareVersion())->toBe(BySquareVersion::V1_2_0)
        ->and(ConfigGuard::bySquareDeburr())->toBeTrue();
});

it('coerces env-style values', function (): void {
    config([
        'qr.error_correction' => 'h',
        'qr.boost_error_correction' => 'false',
        'qr.mask' => '5',
        'qr.svg.size' => null,
        'qr.svg.module_radius' => '0.25',
        'qr.svg.finder_color' => '#F00',
        'qr.cache.store' => 'redis',
        'qr.blade.component' => '',
        'qr.payments.bysquare.version' => '1.0.0',
    ]);

    expect(ConfigGuard::errorCorrection())->toBe(ErrorCorrection::High)
        ->and(ConfigGuard::boostErrorCorrection())->toBeFalse()
        ->and(ConfigGuard::mask())->toBe(5)
        ->and(ConfigGuard::svgSize())->toBeNull()
        ->and(ConfigGuard::moduleRadius())->toBe(0.25)
        ->and(ConfigGuard::finderColor()?->toSvg())->toBe('#f00')
        ->and(ConfigGuard::cacheStore())->toBe('redis')
        ->and(ConfigGuard::bladeComponent())->toBeNull()
        ->and(ConfigGuard::bySquareVersion())->toBe(BySquareVersion::V1_0_0);

    config(['qr.payments.bysquare.version' => BySquareVersion::V1_1_0, 'qr.blade.component' => null]);

    expect(ConfigGuard::bySquareVersion())->toBe(BySquareVersion::V1_1_0)
        ->and(ConfigGuard::bladeComponent())->toBeNull();
});

it('rejects invalid values naming the key', function (string $key, mixed $value, Closure $read): void {
    config([$key => $value]);

    expect($read)->toThrow(InvalidQrConfigException::class, $key);
})->with([
    ['qr.error_correction', 'X', fn () => ConfigGuard::errorCorrection()],
    ['qr.versions.min', 41, fn () => ConfigGuard::minVersion()],
    ['qr.versions.min', 30, fn () => config(['qr.versions.max' => 10]) ?? ConfigGuard::minVersion()],
    ['qr.mask', 8, fn () => ConfigGuard::mask()],
    ['qr.mask', 'x', fn () => ConfigGuard::mask()],
    ['qr.eci', 'sometimes', fn () => ConfigGuard::eci()],
    ['qr.svg.size', 0, fn () => ConfigGuard::svgSize()],
    ['qr.svg.size', 9000, fn () => ConfigGuard::svgSize()],
    ['qr.svg.margin', 65, fn () => ConfigGuard::svgMargin()],
    ['qr.svg.foreground', 'red" onload="x', fn () => ConfigGuard::svgForeground()],
    ['qr.svg.background', 42, fn () => ConfigGuard::svgBackground()],
    ['qr.svg.module_style', 'hearts', fn () => ConfigGuard::moduleStyle()],
    ['qr.svg.module_radius', 0, fn () => ConfigGuard::moduleRadius()],
    ['qr.svg.module_radius', 0.6, fn () => ConfigGuard::moduleRadius()],
    ['qr.svg.module_radius', 'round', fn () => ConfigGuard::moduleRadius()],
    ['qr.svg.finder_style', 'dots', fn () => ConfigGuard::finderStyle()],
    ['qr.svg.finder_color', 'url(#x)', fn () => ConfigGuard::finderColor()],
    ['qr.response.max_age', -1, fn () => ConfigGuard::responseMaxAge()],
    ['qr.memo.entries', -1, fn () => ConfigGuard::memoEntries()],
    ['qr.cache.store', 5, fn () => ConfigGuard::cacheStore()],
    ['qr.cache.ttl', 0, fn () => ConfigGuard::cacheTtl()],
    ['qr.cache.prefix', ['qr'], fn () => ConfigGuard::cachePrefix()],
    ['qr.blade.component', '<script>', fn () => ConfigGuard::bladeComponent()],
    ['qr.blade.component', 5, fn () => ConfigGuard::bladeComponent()],
    ['qr.payments.epc.version', '003', fn () => ConfigGuard::epcVersion()],
    ['qr.payments.epc.charset', 'ascii', fn () => ConfigGuard::epcCharset()],
    ['qr.payments.bysquare.version', '9.9.9', fn () => ConfigGuard::bySquareVersion()],
    ['qr.payments.bysquare.version', 2, fn () => ConfigGuard::bySquareVersion()],
]);

it('reports the max version bounded by the min version', function (): void {
    config(['qr.versions.min' => 5, 'qr.versions.max' => 4]);

    ConfigGuard::maxVersion();
})->throws(InvalidQrConfigException::class);

it('reports the offending key on the exception', function (): void {
    config(['qr.mask' => 99]);

    try {
        ConfigGuard::mask();
    } catch (InvalidQrConfigException $e) {
        expect($e->field)->toBe('qr.mask')
            ->and($e->reason)->toBe('invalid_config')
            ->and($e->getMessage())->not->toContain('99');

        return;
    }

    $this->fail('expected an exception');
});

it('refuses a junk integer string instead of casting it (strict config)', function (string $key, mixed $value, Closure $read): void {
    config([$key => $value]);

    expect($read)->toThrow(InvalidQrConfigException::class, $key);
})->with([
    ['qr.versions.min', 'five', fn () => ConfigGuard::minVersion()],
    ['qr.versions.max', '40.0', fn () => ConfigGuard::maxVersion()],
    ['qr.mask', '5.5', fn () => ConfigGuard::mask()],
    ['qr.svg.size', '1e3', fn () => ConfigGuard::svgSize()],
    ['qr.svg.size', true, fn () => ConfigGuard::svgSize()],
    ['qr.svg.margin', 'four', fn () => ConfigGuard::svgMargin()],
    ['qr.response.max_age', 'day', fn () => ConfigGuard::responseMaxAge()],
    ['qr.memo.entries', '64abc', fn () => ConfigGuard::memoEntries()],
    ['qr.cache.ttl', 'five', fn () => ConfigGuard::cacheTtl()],
]);

it('reads canonical integer strings from env (strict config)', function (): void {
    config([
        'qr.versions.min' => '2',
        'qr.versions.max' => '30',
        'qr.svg.size' => '512',
        'qr.svg.margin' => '0',
        'qr.cache.ttl' => '60',
    ]);

    expect(ConfigGuard::minVersion())->toBe(2)
        ->and(ConfigGuard::maxVersion())->toBe(30)
        ->and(ConfigGuard::svgSize())->toBe(512)
        ->and(ConfigGuard::svgMargin())->toBe(0)
        ->and(ConfigGuard::cacheTtl())->toBe(60);
});

it('reads a blank value as not set, so the documented default applies (strict config)', function (string $blank): void {
    foreach ([
        'qr.error_correction', 'qr.boost_error_correction', 'qr.versions.min', 'qr.versions.max', 'qr.mask',
        'qr.eci', 'qr.kanji', 'qr.svg.size', 'qr.svg.margin', 'qr.svg.foreground', 'qr.svg.background',
        'qr.svg.module_style', 'qr.svg.module_radius', 'qr.svg.finder_style', 'qr.svg.finder_color',
        'qr.svg.xml_declaration', 'qr.response.max_age', 'qr.response.immutable', 'qr.memo.entries',
        'qr.cache.enabled', 'qr.cache.store', 'qr.cache.ttl', 'qr.cache.prefix', 'qr.blade.component',
        'qr.payments.epc.version', 'qr.payments.epc.charset', 'qr.payments.epc.strict_charset',
        'qr.payments.bysquare.version', 'qr.payments.bysquare.deburr',
    ] as $key) {
        config([$key => $blank]);
    }

    expect(ConfigGuard::errorCorrection())->toBe(ErrorCorrection::Medium)
        ->and(ConfigGuard::boostErrorCorrection())->toBeTrue()
        ->and(ConfigGuard::minVersion())->toBe(1)
        ->and(ConfigGuard::maxVersion())->toBe(40)
        ->and(ConfigGuard::mask())->toBeNull()
        ->and(ConfigGuard::eci())->toBe(EciMode::Auto)
        ->and(ConfigGuard::kanji())->toBeFalse()
        ->and(ConfigGuard::svgSize())->toBeNull()
        ->and(ConfigGuard::svgMargin())->toBe(4)
        ->and(ConfigGuard::svgForeground()->toSvg())->toBe('#000000')
        ->and(ConfigGuard::svgBackground()->toSvg())->toBe('#ffffff')
        ->and(ConfigGuard::moduleStyle())->toBe(ModuleStyle::Square)
        ->and(ConfigGuard::moduleRadius())->toBe(0.5)
        ->and(ConfigGuard::finderStyle())->toBe(FinderStyle::Square)
        ->and(ConfigGuard::finderColor())->toBeNull()
        ->and(ConfigGuard::xmlDeclaration())->toBeFalse()
        ->and(ConfigGuard::responseMaxAge())->toBe(86400)
        ->and(ConfigGuard::responseImmutable())->toBeFalse()
        ->and(ConfigGuard::memoEntries())->toBe(64)
        ->and(ConfigGuard::cacheEnabled())->toBeFalse()
        ->and(ConfigGuard::cacheStore())->toBeNull()
        ->and(ConfigGuard::cacheTtl())->toBe(86400)
        ->and(ConfigGuard::cachePrefix())->toBe('qr')
        ->and(ConfigGuard::bladeComponent())->toBeNull()
        ->and(ConfigGuard::epcVersion())->toBe(EpcVersion::V002)
        ->and(ConfigGuard::epcCharset())->toBe(EpcCharset::Utf8)
        ->and(ConfigGuard::epcStrictCharset())->toBeFalse()
        ->and(ConfigGuard::bySquareVersion())->toBe(BySquareVersion::V1_2_0)
        ->and(ConfigGuard::bySquareDeburr())->toBeTrue();
})->with(['empty' => '', 'whitespace' => '  ']);

it('still refuses junk once blank reads as not set (strict config)', function (string $key, mixed $value, Closure $read): void {
    config([$key => $value]);

    expect($read)->toThrow(InvalidQrConfigException::class, $key);
})->with([
    ['qr.error_correction', 'medium-ish', fn () => ConfigGuard::errorCorrection()],
    ['qr.eci', 'Auto', fn () => ConfigGuard::eci()],
    ['qr.svg.foreground', 'blak', fn () => ConfigGuard::svgForeground()],
    ['qr.svg.finder_color', 'blak', fn () => ConfigGuard::finderColor()],
    ['qr.svg.module_radius', 'half', fn () => ConfigGuard::moduleRadius()],
    ['qr.blade.component', 'qr code', fn () => ConfigGuard::bladeComponent()],
    ['qr.payments.epc.version', '2', fn () => ConfigGuard::epcVersion()],
    ['qr.payments.bysquare.version', '1.2', fn () => ConfigGuard::bySquareVersion()],
]);
