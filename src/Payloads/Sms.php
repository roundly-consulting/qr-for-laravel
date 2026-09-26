<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads;

use Illuminate\Contracts\Translation\Translator;
use RoundlyConsulting\Qr\Contracts\Payload;
use RoundlyConsulting\Qr\DataTransferObjects\PayloadRequirements;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Enums\SmsFormat;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;

/**
 * A text message: `SMSTO:+421…:message` or the RFC 5724 `sms:+421…?body=message` form.
 */
final readonly class Sms implements Payload
{
    public string $number;

    /**
     * @throws InvalidPayloadException
     */
    public function __construct(string $number, public ?string $message = null, public SmsFormat $format = SmsFormat::Smsto)
    {
        $this->number = Phone::normalize($number, 'Sms', 'number');
    }

    public function toQrString(): string
    {
        $hasMessage = $this->message !== null && $this->message !== '';

        return match ($this->format) {
            SmsFormat::Smsto => 'SMSTO:'.$this->number.($hasMessage ? ':'.$this->message : ''),
            SmsFormat::Uri => 'sms:'.$this->number.($hasMessage ? '?body='.rawurlencode((string) $this->message) : ''),
        };
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
        return (string) $translator->get('qr::qr.descriptions.sms');
    }
}
