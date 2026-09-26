<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Exceptions;

use Throwable;

/**
 * A payload field is missing or invalid. Names the payload type, the field and a reason
 * key — never the field's value. Not final: the banking and payment exceptions extend it.
 */
class InvalidPayloadException extends QrException
{
    public const string REASON_REQUIRED = 'required';

    public const string REASON_TOO_LONG = 'too_long';

    public const string REASON_TOO_SHORT = 'too_short';

    public const string REASON_INVALID_FORMAT = 'invalid_format';

    public const string REASON_OUT_OF_RANGE = 'out_of_range';

    public const string REASON_UNSUPPORTED_SCHEME = 'unsupported_scheme';

    public const string REASON_MUTUALLY_EXCLUSIVE = 'mutually_exclusive';

    public const string REASON_UNSUPPORTED_IN_VERSION = 'unsupported_in_version';

    public const string REASON_UNREPRESENTABLE = 'unrepresentable';

    public string $payloadType = '';

    /**
     * Final so the `new static` named constructors stay safe for subclasses.
     */
    final public function __construct(string $message = '', string $reason = self::REASON_ERROR, ?string $field = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $reason, $field, $previous);
    }

    public static function required(string $payloadType, string $field): static
    {
        return static::make($payloadType, $field, self::REASON_REQUIRED, 'is required');
    }

    public static function tooLong(string $payloadType, string $field, int $max): static
    {
        return static::make($payloadType, $field, self::REASON_TOO_LONG, sprintf('exceeds the maximum length of %d', $max));
    }

    public static function tooShort(string $payloadType, string $field, int $min): static
    {
        return static::make($payloadType, $field, self::REASON_TOO_SHORT, sprintf('is shorter than the minimum length of %d', $min));
    }

    public static function invalidFormat(string $payloadType, string $field): static
    {
        return static::make($payloadType, $field, self::REASON_INVALID_FORMAT, 'is not in a valid format');
    }

    public static function outOfRange(string $payloadType, string $field): static
    {
        return static::make($payloadType, $field, self::REASON_OUT_OF_RANGE, 'is out of range');
    }

    public static function unsupportedScheme(string $payloadType, string $field): static
    {
        return static::make($payloadType, $field, self::REASON_UNSUPPORTED_SCHEME, 'uses a scheme that is not allowed');
    }

    public static function mutuallyExclusive(string $payloadType, string $field, string $other): static
    {
        return static::make($payloadType, $field, self::REASON_MUTUALLY_EXCLUSIVE, sprintf('cannot be combined with [%s]', $other));
    }

    public static function unsupportedInVersion(string $payloadType, string $field, string $version): static
    {
        return static::make($payloadType, $field, self::REASON_UNSUPPORTED_IN_VERSION, sprintf('is not supported by version %s', $version));
    }

    public static function unrepresentable(string $payloadType, string $field, string $charset): static
    {
        return static::make($payloadType, $field, self::REASON_UNREPRESENTABLE, sprintf('contains characters that %s cannot represent', $charset));
    }

    protected static function make(string $payloadType, string $field, string $reason, string $problem): static
    {
        $exception = new static(sprintf('Invalid %s payload: [%s] %s.', $payloadType, $field, $problem), $reason, $field);
        $exception->payloadType = $payloadType;

        return $exception;
    }
}
