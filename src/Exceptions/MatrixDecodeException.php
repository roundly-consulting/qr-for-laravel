<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Exceptions;

use RoundlyConsulting\Qr\Testing\MatrixDecoder;

/**
 * {@see MatrixDecoder} could not read a matrix: an invalid
 * size, unreadable format or version information, uncorrectable codewords, or a malformed
 * segment stream.
 */
final class MatrixDecodeException extends QrException
{
    public const string REASON_DECODE_SIZE = 'decode_size';

    public const string REASON_DECODE_FORMAT = 'decode_format';

    public const string REASON_DECODE_VERSION = 'decode_version';

    public const string REASON_DECODE_ERROR_CORRECTION = 'decode_error_correction';

    public const string REASON_DECODE_SEGMENT = 'decode_segment';

    public static function size(int $size): self
    {
        return new self(sprintf('A %d-module matrix is not a QR Code Model 2 symbol size (21-177, step 4).', $size), self::REASON_DECODE_SIZE);
    }

    public static function format(): self
    {
        return new self('Neither copy of the format information decodes to a valid BCH code word.', self::REASON_DECODE_FORMAT);
    }

    public static function version(): self
    {
        return new self('The version information does not match the symbol size.', self::REASON_DECODE_VERSION);
    }

    public static function errorCorrection(int $block): self
    {
        return new self(sprintf('Block %d has a non-zero Reed-Solomon syndrome.', $block), self::REASON_DECODE_ERROR_CORRECTION);
    }

    public static function segment(string $problem): self
    {
        return new self(sprintf('Malformed segment stream: %s.', $problem), self::REASON_DECODE_SEGMENT);
    }
}
