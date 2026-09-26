<?php

declare(strict_types=1);

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
]);

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

arch('the encoder core is framework-free')
    ->expect('RoundlyConsulting\Qr\Encoder')
    ->not->toUse('Illuminate');

arch('encoder internals stay behind the public encoder surface')
    ->expect('RoundlyConsulting\Qr\Encoder')
    ->not->toUse(['config', 'app', '__']);
