<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Banking;

/**
 * European Economic Area countries: the EU 27 plus Iceland, Liechtenstein and Norway.
 * EPC069-12 version 002 lets a payment omit the BIC only for IBANs from these countries.
 */
final class Eea
{
    /** @var list<string> */
    public const array COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT',
        'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'IS', 'LI', 'NO',
    ];

    public static function contains(string $country): bool
    {
        return in_array($country, self::COUNTRIES, true);
    }
}
