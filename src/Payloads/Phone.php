<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads;

use Illuminate\Contracts\Translation\Translator;
use RoundlyConsulting\Qr\Contracts\Payload;
use RoundlyConsulting\Qr\DataTransferObjects\PayloadRequirements;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;

/**
 * A `tel:` link (RFC 3966). Visual separators (spaces, dashes, dots, parentheses,
 * slashes) are stripped.
 */
final readonly class Phone implements Payload
{
    public string $number;

    /**
     * @throws InvalidPayloadException
     */
    public function __construct(string $number)
    {
        $this->number = self::normalize($number, 'Phone', 'number');
    }

    /**
     * @throws InvalidPayloadException
     */
    public static function normalize(string $number, string $payloadType, string $field): string
    {
        $stripped = (string) preg_replace('/[\s\x{00A0}\-.()\/]/u', '', $number);

        if ($stripped === '') {
            throw InvalidPayloadException::required($payloadType, $field);
        }

        if (preg_match('/^\+?[0-9]{3,20}$/', $stripped) !== 1) {
            throw InvalidPayloadException::invalidFormat($payloadType, $field);
        }

        return $stripped;
    }

    public function toQrString(): string
    {
        return 'tel:'.$this->number;
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
        return (string) $translator->get('qr::qr.descriptions.phone');
    }
}
