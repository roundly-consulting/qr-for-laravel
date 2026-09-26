<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Support;

/**
 * The `php artisan about` payload.
 *
 * Reads raw configuration rather than {@see ConfigGuard}, so `about` still renders on a
 * misconfigured host instead of throwing at the diagnostic that would explain it. Shows
 * configuration only — never the cache store's name.
 *
 * @internal
 */
final class AboutSection
{
    /**
     * @return array<string, string>
     */
    public static function payload(): array
    {
        return [
            'Error correction' => sprintf(
                '%s, boost %s',
                strtoupper(self::text(config('qr.error_correction'), 'M')),
                self::onOff(config('qr.boost_error_correction'), true),
            ),
            'Versions' => sprintf('%s–%s', self::text(config('qr.versions.min'), '1'), self::text(config('qr.versions.max'), '40')),
            'Mask' => config('qr.mask') === null ? 'auto' : self::text(config('qr.mask'), 'auto'),
            'ECI' => self::text(config('qr.eci'), 'auto'),
            'Kanji' => self::onOff(config('qr.kanji'), false),
            'SVG' => sprintf(
                '%s, margin %s, %s/%s',
                config('qr.svg.size') === null ? 'responsive' : self::text(config('qr.svg.size'), '256').'px',
                self::text(config('qr.svg.margin'), '4'),
                self::text(config('qr.svg.module_style'), 'square'),
                self::text(config('qr.svg.finder_style'), 'square'),
            ),
            'Memo' => self::memo(),
            'Cache' => self::cache(),
            'Blade component' => self::bladeComponent(),
            'EPC' => sprintf(
                'v%s, %s',
                self::text(config('qr.payments.epc.version'), '002'),
                self::text(config('qr.payments.epc.charset'), 'utf-8'),
            ),
            'PAY by square' => sprintf(
                '%s, deburr %s',
                self::text(config('qr.payments.bysquare.version'), '1.2.0'),
                self::onOff(config('qr.payments.bysquare.deburr'), true),
            ),
        ];
    }

    private static function memo(): string
    {
        $entries = config('qr.memo.entries');

        return in_array($entries, [0, '0'], true) ? 'OFF' : self::text($entries, '64').' entries';
    }

    private static function cache(): string
    {
        if (self::onOff(config('qr.cache.enabled'), false) === 'OFF') {
            return 'OFF';
        }

        return sprintf(
            'ON (%s) %ss',
            config('qr.cache.store') === null ? 'default store' : 'custom store',
            self::text(config('qr.cache.ttl'), '86400'),
        );
    }

    private static function bladeComponent(): string
    {
        $component = config('qr.blade.component');

        return is_string($component) && $component !== '' ? '<x-'.$component.'>' : 'OFF';
    }

    private static function text(mixed $value, string $default): string
    {
        return is_scalar($value) && ! is_bool($value) ? (string) $value : $default;
    }

    private static function onOff(mixed $value, bool $default): string
    {
        $flag = $value === null ? $default : (filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default);

        return $flag ? 'ON' : 'OFF';
    }
}
