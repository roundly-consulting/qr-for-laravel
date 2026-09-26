<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Banking;

/**
 * IBAN length and BBAN structure per country (ISO 13616), as published in the SWIFT IBAN
 * Registry. Structure notation: `n` digits, `a` upper-case letters, `c` alphanumerics,
 * `!` fixed length (e.g. `4!n6!n10!n`).
 *
 * Refresh: re-read the current SWIFT IBAN Registry, update the entries, bump RELEASE and
 * the pinned count in the registry test.
 */
final class IbanRegistry
{
    /** Registry data as of this date. */
    public const string RELEASE = '2024-04';

    /** @var array<string, array{0: int, 1: string}> country => [IBAN length, BBAN structure] */
    public const array COUNTRIES = [
        'AD' => [24, '4!n4!n12!c'],
        'AE' => [23, '3!n16!n'],
        'AL' => [28, '8!n16!c'],
        'AT' => [20, '5!n11!n'],
        'AX' => [18, '3!n11!n'],
        'AZ' => [28, '4!a20!c'],
        'BA' => [20, '3!n3!n8!n2!n'],
        'BE' => [16, '3!n7!n2!n'],
        'BG' => [22, '4!a4!n2!n8!c'],
        'BH' => [22, '4!a14!c'],
        'BI' => [27, '5!n5!n11!n2!n'],
        'BL' => [27, '5!n5!n11!c2!n'],
        'BR' => [29, '8!n5!n10!n1!a1!c'],
        'BY' => [28, '4!c4!n16!c'],
        'CH' => [21, '5!n12!c'],
        'CR' => [22, '4!n14!n'],
        'CY' => [28, '3!n5!n16!c'],
        'CZ' => [24, '4!n6!n10!n'],
        'DE' => [22, '8!n10!n'],
        'DJ' => [27, '5!n5!n11!n2!n'],
        'DK' => [18, '4!n9!n1!n'],
        'DO' => [28, '4!c20!n'],
        'EE' => [20, '2!n2!n11!n1!n'],
        'EG' => [29, '4!n4!n17!n'],
        'ES' => [24, '4!n4!n1!n1!n10!n'],
        'FI' => [18, '3!n11!n'],
        'FK' => [18, '2!a12!n'],
        'FO' => [18, '4!n9!n1!n'],
        'FR' => [27, '5!n5!n11!c2!n'],
        'GB' => [22, '4!a6!n8!n'],
        'GE' => [22, '2!a16!n'],
        'GF' => [27, '5!n5!n11!c2!n'],
        'GG' => [22, '4!a6!n8!n'],
        'GI' => [23, '4!a15!c'],
        'GL' => [18, '4!n9!n1!n'],
        'GP' => [27, '5!n5!n11!c2!n'],
        'GR' => [27, '3!n4!n16!c'],
        'GT' => [28, '4!c20!c'],
        'HR' => [21, '7!n10!n'],
        'HU' => [28, '3!n4!n1!n15!n1!n'],
        'IE' => [22, '4!a6!n8!n'],
        'IL' => [23, '3!n3!n13!n'],
        'IM' => [22, '4!a6!n8!n'],
        'IQ' => [23, '4!a3!n12!n'],
        'IS' => [26, '4!n2!n6!n10!n'],
        'IT' => [27, '1!a5!n5!n12!c'],
        'JE' => [22, '4!a6!n8!n'],
        'JO' => [30, '4!a4!n18!c'],
        'KW' => [30, '4!a22!c'],
        'KZ' => [20, '3!n13!c'],
        'LB' => [28, '4!n20!c'],
        'LC' => [32, '4!a24!c'],
        'LI' => [21, '5!n12!c'],
        'LT' => [20, '5!n11!n'],
        'LU' => [20, '3!n13!c'],
        'LV' => [21, '4!a13!c'],
        'LY' => [25, '3!n3!n15!n'],
        'MC' => [27, '5!n5!n11!c2!n'],
        'MD' => [24, '2!c18!c'],
        'ME' => [22, '3!n13!n2!n'],
        'MF' => [27, '5!n5!n11!c2!n'],
        'MK' => [19, '3!n10!c2!n'],
        'MN' => [20, '4!n12!n'],
        'MQ' => [27, '5!n5!n11!c2!n'],
        'MR' => [27, '5!n5!n11!n2!n'],
        'MT' => [31, '4!a5!n18!c'],
        'MU' => [30, '4!a2!n2!n12!n3!n3!a'],
        'NC' => [27, '5!n5!n11!c2!n'],
        'NI' => [28, '4!a20!n'],
        'NL' => [18, '4!a10!n'],
        'NO' => [15, '4!n6!n1!n'],
        'OM' => [23, '3!n16!c'],
        'PF' => [27, '5!n5!n11!c2!n'],
        'PK' => [24, '4!a16!c'],
        'PL' => [28, '8!n16!n'],
        'PM' => [27, '5!n5!n11!c2!n'],
        'PS' => [29, '4!a21!c'],
        'PT' => [25, '4!n4!n11!n2!n'],
        'QA' => [29, '4!a21!c'],
        'RE' => [27, '5!n5!n11!c2!n'],
        'RO' => [24, '4!a16!c'],
        'RS' => [22, '3!n13!n2!n'],
        'RU' => [33, '9!n5!n15!c'],
        'SA' => [24, '2!n18!c'],
        'SC' => [31, '4!a2!n2!n16!n3!a'],
        'SD' => [18, '2!n12!n'],
        'SE' => [24, '3!n16!n1!n'],
        'SI' => [19, '5!n8!n2!n'],
        'SK' => [24, '4!n6!n10!n'],
        'SM' => [27, '1!a5!n5!n12!c'],
        'SO' => [23, '4!n3!n12!n'],
        'ST' => [25, '4!n4!n11!n2!n'],
        'SV' => [28, '4!a20!n'],
        'TF' => [27, '5!n5!n11!c2!n'],
        'TL' => [23, '3!n14!n2!n'],
        'TN' => [24, '2!n3!n13!n2!n'],
        'TR' => [26, '5!n1!n16!c'],
        'UA' => [29, '6!n19!c'],
        'VA' => [22, '3!n15!n'],
        'VG' => [24, '4!a16!n'],
        'WF' => [27, '5!n5!n11!c2!n'],
        'XK' => [20, '4!n10!n2!n'],
        'YT' => [27, '5!n5!n11!c2!n'],
    ];

    /** @var array<string, string> */
    private static array $patterns = [];

    public static function has(string $country): bool
    {
        return isset(self::COUNTRIES[$country]);
    }

    public static function length(string $country): ?int
    {
        return self::COUNTRIES[$country][0] ?? null;
    }

    /**
     * The BBAN structure as an anchored regular expression.
     */
    public static function pattern(string $country): ?string
    {
        if (! isset(self::COUNTRIES[$country])) {
            return null;
        }

        return self::$patterns[$country] ??= '/^'.(string) preg_replace_callback(
            '/(\\d+)!([nac])/',
            static fn (array $part): string => ['n' => '[0-9]', 'a' => '[A-Z]', 'c' => '[A-Z0-9]'][$part[2]].'{'.$part[1].'}',
            self::COUNTRIES[$country][1],
        ).'$/';
    }
}
