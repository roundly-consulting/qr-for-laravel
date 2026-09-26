<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads\Payments\BySquare;

use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;
use RoundlyConsulting\Qr\Support\TextNormalizer;

/**
 * The payee (by square 1.1.0+; the name is mandatory from 1.2.0).
 */
final readonly class Beneficiary
{
    public const int MAX_LENGTH = 70;

    public string $name;

    public ?string $street;

    public ?string $city;

    /**
     * @throws InvalidPayloadException
     */
    public function __construct(string $name, ?string $street = null, ?string $city = null)
    {
        $this->name = self::field($name, 'beneficiary.name') ?? throw InvalidPayloadException::required(PayBySquare::TYPE, 'beneficiary.name');
        $this->street = self::field($street, 'beneficiary.street');
        $this->city = self::field($city, 'beneficiary.city');
    }

    private static function field(?string $value, string $field): ?string
    {
        $value = $value === null ? null : trim(TextNormalizer::clean($value));

        if ($value === null || $value === '') {
            return null;
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidPayloadException::tooLong(PayBySquare::TYPE, $field, self::MAX_LENGTH);
        }

        return $value;
    }
}
