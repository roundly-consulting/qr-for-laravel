<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Exceptions;

final class InvalidCreditorReferenceException extends InvalidPayloadException
{
    public const string REASON_CREDITOR_REFERENCE_FORMAT = 'creditor_reference_format';

    public const string REASON_CREDITOR_REFERENCE_CHECKSUM = 'creditor_reference_checksum';

    public static function format(string $field = 'reference'): self
    {
        return self::make('Creditor reference', $field, self::REASON_CREDITOR_REFERENCE_FORMAT, 'is not an ISO 11649 RF reference');
    }

    public static function checksum(string $field = 'reference'): self
    {
        return self::make('Creditor reference', $field, self::REASON_CREDITOR_REFERENCE_CHECKSUM, 'fails the ISO 7064 MOD 97-10 check');
    }
}
