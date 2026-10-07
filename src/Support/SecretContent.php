<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Support;

/**
 * Recognises raw data that is really a 2FA seed or a Wi-Fi credential, whichever payload
 * type carries it: after leading whitespace (Unicode spaces and a byte order mark too) and
 * in any case, it starts with `otpauth:`, `otpauth-migration:` or `WIFI:`.
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
        return str_starts_with(strtolower(self::withoutLeadingSpace($data)), $prefix);
    }

    /**
     * Strips leading whitespace, NUL, Unicode spaces and a byte order mark — text read from a
     * file or pasted from a document must not hide the prefix (scanners skip a leading BOM).
     * Invalid UTF-8 is scrubbed first; only the ASCII prefix matters here.
     */
    private static function withoutLeadingSpace(string $data): string
    {
        return (string) preg_replace('/^[\s\x{0}\x{FEFF}\x{200B}\p{Z}]+/u', '', mb_scrub($data, 'UTF-8'));
    }
}
