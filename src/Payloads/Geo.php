<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads;

use Illuminate\Contracts\Translation\Translator;
use RoundlyConsulting\Qr\Contracts\Payload;
use RoundlyConsulting\Qr\DataTransferObjects\PayloadRequirements;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;

/**
 * A `geo:` URI (RFC 5870) with up to seven decimals, formatted independently of the locale.
 */
final readonly class Geo implements Payload
{
    /**
     * @throws InvalidPayloadException
     */
    public function __construct(public float $latitude, public float $longitude)
    {
        if (! is_finite($latitude) || $latitude < -90.0 || $latitude > 90.0) {
            throw InvalidPayloadException::outOfRange('Geo', 'latitude');
        }

        if (! is_finite($longitude) || $longitude < -180.0 || $longitude > 180.0) {
            throw InvalidPayloadException::outOfRange('Geo', 'longitude');
        }
    }

    public function toQrString(): string
    {
        return 'geo:'.self::format($this->latitude).','.self::format($this->longitude);
    }

    public function requirements(): PayloadRequirements
    {
        return new PayloadRequirements;
    }

    public function sensitivity(): Sensitivity
    {
        return Sensitivity::Personal;
    }

    public function description(Translator $translator): string
    {
        return (string) $translator->get('qr::qr.descriptions.geo');
    }

    private static function format(float $value): string
    {
        $formatted = rtrim(rtrim(sprintf('%.7F', $value), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }
}
