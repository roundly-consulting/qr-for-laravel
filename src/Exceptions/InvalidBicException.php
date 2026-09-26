<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Exceptions;

final class InvalidBicException extends InvalidPayloadException
{
    public const string REASON_BIC_FORMAT = 'bic_format';

    public const string REASON_BIC_COUNTRY = 'bic_country';

    public static function format(string $field = 'bic'): self
    {
        return self::make('BIC', $field, self::REASON_BIC_FORMAT, 'is not an ISO 9362 BIC (8 or 11 characters)');
    }

    public static function country(string $field = 'bic'): self
    {
        return self::make('BIC', $field, self::REASON_BIC_COUNTRY, 'contains an unknown country code');
    }
}
