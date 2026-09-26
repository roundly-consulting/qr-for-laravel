<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Exceptions;

use RoundlyConsulting\Qr\Enums\ErrorCorrection;

/**
 * The data does not fit in any symbol of the allowed version window. Carries bit counts,
 * never the data.
 */
final class DataTooLongException extends QrException
{
    public const string REASON_DATA_TOO_LONG = 'data_too_long';

    public const string REASON_INPUT_TOO_LONG = 'input_too_long';

    public ?int $neededBits = null;

    public ?int $capacityBits = null;

    public ?int $minVersion = null;

    public ?int $maxVersion = null;

    public ?ErrorCorrection $errorCorrection = null;

    public static function capacity(int $neededBits, int $capacityBits, int $minVersion, int $maxVersion, ErrorCorrection $errorCorrection): self
    {
        $exception = new self(sprintf(
            'The data needs %d bits but versions %d-%d at error correction %s hold at most %d bits.',
            $neededBits,
            $minVersion,
            $maxVersion,
            $errorCorrection->value,
            $capacityBits,
        ), self::REASON_DATA_TOO_LONG);

        $exception->neededBits = $neededBits;
        $exception->capacityBits = $capacityBits;
        $exception->minVersion = $minVersion;
        $exception->maxVersion = $maxVersion;
        $exception->errorCorrection = $errorCorrection;

        return $exception;
    }

    public static function input(int $bytes, int $maxBytes): self
    {
        return new self(sprintf(
            'The input is %d bytes long; no QR symbol holds more than %d characters.',
            $bytes,
            $maxBytes,
        ), self::REASON_INPUT_TOO_LONG);
    }
}
