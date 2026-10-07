<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Banking;

/**
 * ISO 7064 MOD 97-10 remainder of an alphanumeric string (letters A = 10 … Z = 35),
 * computed in 9-digit chunks so no arbitrary-precision arithmetic is needed.
 */
final class Mod97
{
    public static function remainder(string $alphanumeric): int
    {
        $digits = '';

        foreach (str_split(strtoupper($alphanumeric)) as $character) {
            $digits .= ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
        }

        $remainder = 0;

        foreach (str_split($digits, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return $remainder;
    }

    /**
     * Whether two check digits are ones an issuer can produce: generation computes
     * 98 − (remainder of the number with `00`), so only 02–98 ever occur. 00, 01 and 99 also
     * leave remainder 1, but no real IBAN or RF reference carries them.
     */
    public static function issuableCheckDigits(string $checkDigits): bool
    {
        return preg_match('/^[0-9]{2}\z/', $checkDigits) === 1
            && (int) $checkDigits >= 2
            && (int) $checkDigits <= 98;
    }
}
