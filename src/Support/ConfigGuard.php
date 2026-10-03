<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Support;

use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\PackageToolkit\Support\ConfigValidator;
use RoundlyConsulting\Qr\Enums\BySquare\BySquareVersion;
use RoundlyConsulting\Qr\Enums\EciMode;
use RoundlyConsulting\Qr\Enums\EpcCharset;
use RoundlyConsulting\Qr\Enums\EpcVersion;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\FinderStyle;
use RoundlyConsulting\Qr\Enums\ModuleStyle;
use RoundlyConsulting\Qr\Exceptions\InvalidColorException;
use RoundlyConsulting\Qr\Exceptions\InvalidQrConfigException;
use RoundlyConsulting\Qr\ValueObjects\Color;

/**
 * Typed, validated reads of `config/qr.php`. A key that is not set — absent, null or blank
 * (`''` or whitespace, a host's `KEY=`) — takes its documented default; a misconfigured key
 * throws {@see InvalidQrConfigException} naming the key — never its value.
 *
 * @internal
 */
final class ConfigGuard
{
    public static function errorCorrection(): ErrorCorrection
    {
        $value = config('qr.error_correction');

        return self::blank($value) ? ErrorCorrection::Medium : ErrorCorrection::fromConfig($value);
    }

    public static function boostErrorCorrection(): bool
    {
        return self::validator()->boolean('qr.boost_error_correction', true);
    }

    public static function minVersion(): int
    {
        $min = self::validator()->integer('qr.versions.min', 1, min: 1, max: 40);

        if ($min > self::validator()->integer('qr.versions.max', 40, min: 1, max: 40)) {
            throw InvalidQrConfigException::invalid('qr.versions.min', 'must not exceed qr.versions.max');
        }

        return $min;
    }

    public static function maxVersion(): int
    {
        return self::validator()->integer('qr.versions.max', 40, min: self::minVersion(), max: 40);
    }

    public static function mask(): ?int
    {
        return self::nullableIntBetween('qr.mask', 0, 7);
    }

    public static function eci(): EciMode
    {
        return self::validator()->enum('qr.eci', EciMode::class, EciMode::Auto);
    }

    public static function kanji(): bool
    {
        return self::validator()->boolean('qr.kanji', false);
    }

    public static function svgSize(): ?int
    {
        return self::nullableIntBetween('qr.svg.size', 1, 8192);
    }

    public static function svgMargin(): int
    {
        return self::validator()->integer('qr.svg.margin', 4, min: 0, max: 64);
    }

    public static function svgForeground(): Color
    {
        return self::color('qr.svg.foreground', self::orDefault(config('qr.svg.foreground'), '#000000'));
    }

    public static function svgBackground(): Color
    {
        return self::color('qr.svg.background', self::orDefault(config('qr.svg.background'), '#ffffff'));
    }

    public static function moduleStyle(): ModuleStyle
    {
        return self::validator()->enum('qr.svg.module_style', ModuleStyle::class, ModuleStyle::Square);
    }

    public static function moduleRadius(): float
    {
        $radius = self::orDefault(config('qr.svg.module_radius'), 0.5);

        if (is_string($radius) && is_numeric($radius)) {
            $radius = (float) $radius;
        }

        if ((! is_int($radius) && ! is_float($radius)) || $radius <= 0 || $radius > 0.5) {
            throw InvalidQrConfigException::invalid('qr.svg.module_radius', 'expected a number with 0 < radius <= 0.5');
        }

        return (float) $radius;
    }

    public static function finderStyle(): FinderStyle
    {
        return self::validator()->enum('qr.svg.finder_style', FinderStyle::class, FinderStyle::Square);
    }

    public static function finderColor(): ?Color
    {
        $value = config('qr.svg.finder_color');

        return self::blank($value) ? null : self::color('qr.svg.finder_color', $value);
    }

    public static function xmlDeclaration(): bool
    {
        return self::validator()->boolean('qr.svg.xml_declaration', false);
    }

    public static function responseMaxAge(): int
    {
        return self::validator()->integer('qr.response.max_age', 86400, min: 0, max: 31536000);
    }

    public static function responseImmutable(): bool
    {
        return self::validator()->boolean('qr.response.immutable', false);
    }

    public static function memoEntries(): int
    {
        return self::validator()->integer('qr.memo.entries', 64, min: 0, max: 100000);
    }

    public static function cacheEnabled(): bool
    {
        return self::validator()->boolean('qr.cache.enabled', false);
    }

    public static function cacheStore(): ?string
    {
        $store = config('qr.cache.store');

        if (self::blank($store)) {
            return null;
        }

        if (! is_string($store)) {
            throw InvalidQrConfigException::invalid('qr.cache.store', 'expected null or a store name');
        }

        return $store;
    }

    public static function cacheTtl(): int
    {
        return self::validator()->integer('qr.cache.ttl', 86400, min: 1, max: 31536000);
    }

    public static function cachePrefix(): string
    {
        return self::blank(config('qr.cache.prefix')) ? 'qr' : self::validator()->requireString('qr.cache.prefix');
    }

    public static function bladeComponent(): ?string
    {
        $component = config('qr.blade.component');

        // Not set — null or blank — means no component.
        if (self::blank($component)) {
            return null;
        }

        if (! is_string($component) || preg_match('/^[a-z0-9][a-z0-9:.\-]*$/i', $component) !== 1) {
            throw InvalidQrConfigException::invalid('qr.blade.component', 'expected null or a component alias such as "qr-code"');
        }

        return $component;
    }

    public static function epcVersion(): EpcVersion
    {
        return self::validator()->enum('qr.payments.epc.version', EpcVersion::class, EpcVersion::V002);
    }

    public static function epcCharset(): EpcCharset
    {
        return self::validator()->enum('qr.payments.epc.charset', EpcCharset::class, EpcCharset::Utf8);
    }

    public static function epcStrictCharset(): bool
    {
        return self::validator()->boolean('qr.payments.epc.strict_charset', false);
    }

    public static function bySquareVersion(): BySquareVersion
    {
        $version = config('qr.payments.bysquare.version');

        if ($version instanceof BySquareVersion) {
            return $version;
        }

        if (self::blank($version)) {
            return BySquareVersion::V1_2_0;
        }

        return (is_string($version) ? BySquareVersion::tryFromSemver($version) : null)
            ?? throw InvalidQrConfigException::invalid('qr.payments.bysquare.version', 'expected one of 1.0.0, 1.1.0, 1.2.0');
    }

    public static function bySquareDeburr(): bool
    {
        return self::validator()->boolean('qr.payments.bysquare.deburr', true);
    }

    private static function validator(): ConfigValidator
    {
        return Config::using(InvalidQrConfigException::class);
    }

    /** Not set: absent, null or a blank string (`''` or whitespace — a host's `KEY=`). */
    public static function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private static function orDefault(mixed $value, mixed $default): mixed
    {
        return self::blank($value) ? $default : $value;
    }

    private static function nullableIntBetween(string $key, int $min, int $max): ?int
    {
        $value = config($key);

        if (self::blank($value)) {
            return null;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value < $min || $value > $max) {
            throw InvalidQrConfigException::invalid($key, sprintf('expected null or an integer between %d and %d', $min, $max));
        }

        return $value;
    }

    private static function color(string $key, mixed $value): Color
    {
        if (! is_string($value)) {
            throw InvalidQrConfigException::invalid($key, 'expected a colour string');
        }

        try {
            return Color::parse($value);
        } catch (InvalidColorException) {
            throw InvalidQrConfigException::invalid($key, 'expected an allow-listed colour (hex, rgb()/rgba(), a CSS named colour, transparent or currentColor)');
        }
    }
}
