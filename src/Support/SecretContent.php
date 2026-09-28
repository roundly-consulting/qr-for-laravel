<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Support;

/**
 * Recognises raw data that is really a 2FA seed or a Wi-Fi credential, whichever payload
 * type carries it: after leading whitespace and in any case, it starts with `otpauth:`,
 * `otpauth-migration:` or `WIFI:`.
 *
 * @internal
 */
final class SecretContent
{
    public static function isOtpauth(string $data): bool
    {
        return self::startsWith($data, 'otpauth:') || self::startsWith($data, 'otpauth-migration:');
    }

    public static function isWifi(string $data): bool
    {
        return self::startsWith($data, 'wifi:');
    }

    public static function isSecret(string $data): bool
    {
        return self::isOtpauth($data) || self::isWifi($data);
    }

    private static function startsWith(string $data, string $prefix): bool
    {
        return str_starts_with(strtolower(ltrim($data)), $prefix);
    }
}
