<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Exceptions;

final class InvalidIbanException extends InvalidPayloadException
{
    public const string REASON_IBAN_FORMAT = 'iban_format';

    public const string REASON_IBAN_COUNTRY = 'iban_country';

    public const string REASON_IBAN_LENGTH = 'iban_length';

    public const string REASON_IBAN_CHECKSUM = 'iban_checksum';

    public static function format(string $field = 'iban'): self
    {
        return self::make('IBAN', $field, self::REASON_IBAN_FORMAT, 'does not have the structure of an IBAN');
    }

    public static function country(string $field = 'iban'): self
    {
        return self::make('IBAN', $field, self::REASON_IBAN_COUNTRY, 'uses a country code without an IBAN format');
    }

    public static function length(string $field = 'iban'): self
    {
        return self::make('IBAN', $field, self::REASON_IBAN_LENGTH, 'has the wrong length for its country');
    }

    public static function checksum(string $field = 'iban'): self
    {
        return self::make('IBAN', $field, self::REASON_IBAN_CHECKSUM, 'fails the ISO 7064 MOD 97-10 check');
    }
}
