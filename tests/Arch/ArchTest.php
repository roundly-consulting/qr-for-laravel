<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Exceptions\BySquareDecodeException;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;
use RoundlyConsulting\Qr\Exceptions\QrException;
use RoundlyConsulting\Testing\Arch\ArchPresets;

ArchPresets::strictTypes('RoundlyConsulting\Qr');

/**
 * Two exception bases are thrown directly AND extended, so they cannot be final:
 * InvalidOptionException (parent of InvalidColorException) and InvalidPayloadException
 * (parent of the banking and payment exceptions). `QrException` is abstract and excluded
 * by the preset itself.
 */
ArchPresets::finalByDefault('RoundlyConsulting\Qr', ignoring: [
    InvalidOptionException::class,
    InvalidPayloadException::class,
    BySquareDecodeException::class,     // parent of CorruptLzmaStreamException
]);

/**
 * The money seam: only Support\Amounts calls money-for-laravel; payment payloads may
 * type-hint Money but nothing else imports it.
 */
arch('only the amounts seam and payment payloads touch money')
    ->expect('RoundlyConsulting\Qr')
    ->not->toUse('RoundlyConsulting\Money')
    ->ignoring([
        'RoundlyConsulting\Qr\Support\Amounts',
        'RoundlyConsulting\Qr\Payloads\Payments',
    ]);

arch('payment payloads only type-hint the Money value object')
    ->expect('RoundlyConsulting\Qr\Payloads\Payments')
    ->not->toUse(['RoundlyConsulting\Money\Currency', 'RoundlyConsulting\Money\Contracts', 'RoundlyConsulting\Money\Facades']);

/**
 * base64 and hashing go through crypto-for-laravel (`Codec\Base64`, `Hash\Digest`).
 * `crc32()` is a checksum, not a crypto primitive, and is not on the list.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Qr');

/**
 * The Dependency Policy as a test. No `alsoAllow`: `require` holds only php, ext-*,
 * illuminate/* and roundly packages.
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../../composer.json');

ArchPresets::noDebuggingLeftovers();

/*
 * Not adopted, with cause:
 *  - swappableModelsAreNotFinal / modelsResolveThroughSeam — no Eloquent model and no
 *    `*_model` key; both would be vacuous.
 *  - morphColumnsUseTheSeam — no migrations; the preset fails on a missing directory by
 *    design.
 *  - modelsGoThroughTheFacade — no `Models`, `Concerns` or `Traits` namespace (the `AsIban`
 *    cast and the rules hold no behaviour a fake would need to see); the preset fails on an
 *    empty scan by design.
 */

arch('no third-party QR vendors are used')
    ->expect(['BaconQrCode', 'Endroid', 'chillerlan', 'SimpleSoftwareIO', 'Zxing'])
    ->not->toBeUsed();

arch('exceptions extend the package base')
    ->expect('RoundlyConsulting\Qr\Exceptions')
    ->classes()
    ->toExtend(QrException::class)
    ->ignoring(QrException::class);

arch('the package base exception is abstract')
    ->expect(QrException::class)
    ->toBeAbstract()
    ->toExtend(RuntimeException::class);

// One case per core: Pest's `->not->toUse()` over a multi-element `expect([...])` fails only
// when EVERY subject uses the target, so one framework-bound core would slip through.
foreach (['Encoder', 'Banking', 'Compression'] as $core) {
    arch("the {$core} core is framework-free")
        ->expect("RoundlyConsulting\\Qr\\{$core}")
        ->not->toUse('Illuminate');
}

arch('encoder internals stay behind the public encoder surface')
    ->expect('RoundlyConsulting\Qr\Encoder')
    ->not->toUse(['config', 'app', '__']);

arch('src only uses allowed vendor roots')
    ->expect('RoundlyConsulting\Qr')
    ->toOnlyUse([
        'RoundlyConsulting\Qr',
        'RoundlyConsulting\Crypto',
        'RoundlyConsulting\Enums',
        'RoundlyConsulting\Money',
        'RoundlyConsulting\PackageToolkit',
        'Illuminate',
        'Carbon',
        'Normalizer',
        'Closure',
        'SensitiveParameter',
        'RuntimeException',
        'Stringable',
        'Throwable',
        // Laravel global helpers used unqualified
        'app',
        'config',
        'class_basename',
    ]);
