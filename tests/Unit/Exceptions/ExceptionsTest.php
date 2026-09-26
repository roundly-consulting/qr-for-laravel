<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\Mode;
use RoundlyConsulting\Qr\Exceptions\DataTooLongException;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;
use RoundlyConsulting\Qr\Exceptions\InvalidQrConfigException;
use RoundlyConsulting\Qr\Exceptions\MatrixDecodeException;
use RoundlyConsulting\Qr\Exceptions\QrException;
use RoundlyConsulting\Qr\Exceptions\UnencodableCharacterException;

it('builds every named constructor with a reason key and no payload value', function (QrException $e, string $reason, ?string $field): void {
    expect($e)->toBeInstanceOf(QrException::class)
        ->and($e->reason)->toBe($reason)
        ->and($e->field)->toBe($field)
        ->and($e->getMessage())->not->toBe('')
        ->and(trans('qr::qr.errors.'.$e->reason))->not->toBe('qr::qr.errors.'.$e->reason);
})->with([
    'capacity' => [fn () => DataTooLongException::capacity(900, 800, 1, 40, ErrorCorrection::High), 'data_too_long', null],
    'input' => [fn () => DataTooLongException::input(8000, 7089), 'input_too_long', null],
    'version range' => [fn () => InvalidOptionException::versionRange(5, 2), 'version_range', 'version'],
    'mask' => [fn () => InvalidOptionException::mask(9), 'mask', 'mask'],
    'size' => [fn () => InvalidOptionException::size(0), 'size', 'size'],
    'margin' => [fn () => InvalidOptionException::margin(-1), 'margin', 'margin'],
    'radius' => [fn () => InvalidOptionException::radius(0.75), 'radius', 'radius'],
    'attribute' => [fn () => InvalidOptionException::attribute(), 'attribute', 'attribute'],
    'ecc' => [fn () => InvalidOptionException::errorCorrection(), 'error_correction', 'errorCorrection'],
    'locked' => [fn () => InvalidOptionException::lockedByPayload('eci', 'EPC'), 'locked_by_payload', 'eci'],
    'bounds' => [fn () => InvalidOptionException::outOfBounds(30, 1, 21), 'out_of_bounds', 'module'],
    'render as' => [fn () => InvalidOptionException::renderAs(), 'render_as', 'as'],
    'unencodable' => [fn () => UnencodableCharacterException::at(Mode::Numeric, 3), 'unencodable_character', 'data'],
    'required' => [fn () => InvalidPayloadException::required('Wifi', 'password'), 'required', 'password'],
    'too long' => [fn () => InvalidPayloadException::tooLong('Wifi', 'ssid', 32), 'too_long', 'ssid'],
    'too short' => [fn () => InvalidPayloadException::tooShort('Wifi', 'password', 8), 'too_short', 'password'],
    'format' => [fn () => InvalidPayloadException::invalidFormat('Email', 'to'), 'invalid_format', 'to'],
    'range' => [fn () => InvalidPayloadException::outOfRange('Geo', 'latitude'), 'out_of_range', 'latitude'],
    'scheme' => [fn () => InvalidPayloadException::unsupportedScheme('Url', 'url'), 'unsupported_scheme', 'url'],
    'exclusive' => [fn () => InvalidPayloadException::mutuallyExclusive('EPC', 'text', 'reference'), 'mutually_exclusive', 'text'],
    'version' => [fn () => InvalidPayloadException::unsupportedInVersion('PAY by square', 'beneficiary', '1.0.0'), 'unsupported_in_version', 'beneficiary'],
    'charset' => [fn () => InvalidPayloadException::unrepresentable('EPC', 'name', 'ISO-8859-1'), 'unrepresentable', 'name'],
    'config' => [fn () => InvalidQrConfigException::invalid('qr.mask', 'bad'), 'invalid_config', 'qr.mask'],
    'decode size' => [fn () => MatrixDecodeException::size(20), 'decode_size', null],
    'decode format' => [fn () => MatrixDecodeException::format(), 'decode_format', null],
    'decode version' => [fn () => MatrixDecodeException::version(), 'decode_version', null],
    'decode rs' => [fn () => MatrixDecodeException::errorCorrection(2), 'decode_error_correction', null],
    'decode segment' => [fn () => MatrixDecodeException::segment('truncated'), 'decode_segment', null],
]);

it('carries the capacity numbers on a data-too-long exception', function (): void {
    $e = DataTooLongException::capacity(900, 800, 2, 13, ErrorCorrection::Medium);

    expect([$e->neededBits, $e->capacityBits, $e->minVersion, $e->maxVersion, $e->errorCorrection])
        ->toBe([900, 800, 2, 13, ErrorCorrection::Medium]);
});

it('records the payload type on payload exceptions', function (): void {
    expect(InvalidPayloadException::required('Wifi', 'password')->payloadType)->toBe('Wifi');
});

it('accepts the toolkit re-throw shape for config exceptions', function (): void {
    $e = new InvalidQrConfigException('Config value [qr.x] is broken.');

    expect($e->reason)->toBe('invalid_config')->and($e->field)->toBeNull();
});
