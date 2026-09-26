<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Exceptions;

/**
 * The serialised payment exceeds its standard's byte limit (EPC: 331 bytes; PAY by
 * square: 65 535 bytes before compression).
 */
final class PaymentPayloadTooLongException extends InvalidPayloadException
{
    public const string REASON_PAYLOAD_TOO_LONG = 'payload_too_long';

    public ?int $bytes = null;

    public ?int $limit = null;

    public static function bytes(string $payloadType, int $bytes, int $limit): self
    {
        $exception = self::make($payloadType, 'payload', self::REASON_PAYLOAD_TOO_LONG, sprintf('is %d bytes; the limit is %d', $bytes, $limit));
        $exception->bytes = $bytes;
        $exception->limit = $limit;

        return $exception;
    }
}
