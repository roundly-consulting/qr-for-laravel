<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Exceptions;

use Throwable;

/**
 * A PAY by square string could not be decoded. The reason names the failing stage —
 * base32hex, header, length, lzma, crc, fields or value — never the payload. Not final:
 * {@see CorruptLzmaStreamException} extends it.
 */
class BySquareDecodeException extends QrException
{
    public const string REASON_BASE32HEX = 'bysquare_base32hex';

    public const string REASON_HEADER = 'bysquare_header';

    public const string REASON_LENGTH = 'bysquare_length';

    public const string REASON_LZMA = 'bysquare_lzma';

    public const string REASON_CRC = 'bysquare_crc';

    public const string REASON_FIELDS = 'bysquare_fields';

    public const string REASON_VALUE = 'bysquare_value';

    final public function __construct(string $message = '', string $reason = self::REASON_ERROR, ?string $field = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $reason, $field, $previous);
    }

    public static function base32hex(): static
    {
        return new static('The PAY by square string is not valid base32hex.', self::REASON_BASE32HEX);
    }

    public static function header(): static
    {
        return new static('The PAY by square header does not describe a supported payment document.', self::REASON_HEADER);
    }

    public static function length(): static
    {
        return new static('The PAY by square length field is inconsistent with the data.', self::REASON_LENGTH);
    }

    public static function crc(): static
    {
        return new static('The PAY by square checksum does not match the data.', self::REASON_CRC);
    }

    public static function fields(): static
    {
        return new static('The PAY by square data has the wrong number of fields for its version.', self::REASON_FIELDS);
    }

    public static function value(string $field, ?Throwable $previous = null): static
    {
        return new static(sprintf('The PAY by square field [%s] holds an invalid value.', $field), self::REASON_VALUE, $field, $previous);
    }
}
