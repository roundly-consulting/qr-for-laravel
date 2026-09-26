<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Exceptions;

/**
 * A payment amount in a currency the payment standard does not support: EPC is euro-only,
 * and both payment standards accept ISO 4217 currencies only.
 */
final class UnsupportedCurrencyException extends InvalidPayloadException
{
    public const string REASON_CURRENCY_NOT_EUR = 'currency_not_eur';

    public const string REASON_CURRENCY_NOT_ISO = 'currency_not_iso';

    public const string REASON_CURRENCY_MISMATCH = 'currency_mismatch';

    public static function notEuro(string $payloadType, string $field = 'amount'): self
    {
        return self::make($payloadType, $field, self::REASON_CURRENCY_NOT_EUR, 'must be in euro');
    }

    public static function notIso(string $payloadType, string $field = 'amount'): self
    {
        return self::make($payloadType, $field, self::REASON_CURRENCY_NOT_ISO, 'must use an ISO 4217 currency');
    }

    public static function mismatch(string $payloadType, string $field): self
    {
        return self::make($payloadType, $field, self::REASON_CURRENCY_MISMATCH, 'must use the same currency as the payment');
    }
}
