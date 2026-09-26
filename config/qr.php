<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Encoding defaults
    |--------------------------------------------------------------------------
    |
    | The default error-correction level (L, M, Q or H), whether the encoder may
    | raise it for free when the chosen version has spare room, the version
    | window (1..40), a forced mask (0..7, or null = lowest ISO penalty), the
    | ECI 26 (UTF-8) policy and whether kanji segments may be used.
    |
    */

    'error_correction' => env('QR_ERROR_CORRECTION', 'M'),

    'boost_error_correction' => env('QR_BOOST_ERROR_CORRECTION', true),

    'versions' => [
        'min' => 1,
        'max' => 40,
    ],

    'mask' => null,

    'eci' => env('QR_ECI', 'auto'),

    'kanji' => false,

    /*
    |--------------------------------------------------------------------------
    | SVG defaults
    |--------------------------------------------------------------------------
    |
    | `size` is the rendered width/height in px (1..8192) or null for a
    | responsive symbol (viewBox only). `margin` is the quiet zone in modules
    | (ISO/IEC 18004 asks for 4). Colours go through an allow-list: hex,
    | rgb()/rgba(), CSS named colours, `transparent` and `currentColor`.
    |
    */

    'svg' => [
        'size' => 256,
        'margin' => 4,
        'foreground' => '#000000',
        'background' => '#ffffff',
        'module_style' => 'square',
        'module_radius' => 0.5,
        'finder_style' => 'square',
        'finder_color' => null,
        'xml_declaration' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP responses
    |--------------------------------------------------------------------------
    |
    | Cache-Control max-age for public and personal payloads. Secret payloads
    | (2FA setup codes, Wi-Fi credentials) are always served `no-store`.
    |
    */

    'response' => [
        'max_age' => 86400,
        'immutable' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    |
    | `memo.entries` bounds the in-process LRU of encoded matrices (public
    | payloads only; 0 disables it). The optional cache stores rendered SVG
    | strings for public payloads in a Laravel cache store.
    |
    */

    'memo' => [
        'entries' => 64,
    ],

    'cache' => [
        'enabled' => env('QR_CACHE', false),
        'store' => env('QR_CACHE_STORE'),
        'ttl' => 86400,
        'prefix' => 'qr',
    ],

    /*
    |--------------------------------------------------------------------------
    | Blade
    |--------------------------------------------------------------------------
    |
    | The component alias (`qr-code` registers <x-qr-code>). Null or an empty
    | string disables the registration.
    |
    */

    'blade' => [
        'component' => 'qr-code',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment payload defaults
    |--------------------------------------------------------------------------
    |
    | EPC069-12 (SEPA credit transfer) version 001|002, character set and the
    | optional SEPA Latin subset restriction; PAY by square specification
    | version 1.0.0|1.1.0|1.2.0 and whether note/beneficiary text is stripped
    | of diacritics.
    |
    */

    'payments' => [
        'epc' => [
            'version' => env('QR_EPC_VERSION', '002'),
            'charset' => 'utf-8',
            'strict_charset' => false,
        ],
        'bysquare' => [
            'version' => env('QR_BYSQUARE_VERSION', '1.2.0'),
            'deburr' => env('QR_BYSQUARE_DEBURR', true),
        ],
    ],

];
