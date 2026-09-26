<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads;

use Illuminate\Contracts\Translation\Translator;
use RoundlyConsulting\Qr\Contracts\Payload;
use RoundlyConsulting\Qr\DataTransferObjects\PayloadRequirements;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;

/**
 * A `mailto:` link (RFC 6068) with optional subject and body.
 */
final readonly class Email implements Payload
{
    /**
     * @throws InvalidPayloadException
     */
    public function __construct(public string $to, public ?string $subject = null, public ?string $body = null)
    {
        self::assertAddress($to, 'Email', 'to');
    }

    /**
     * @throws InvalidPayloadException
     */
    public static function assertAddress(string $address, string $payloadType, string $field): void
    {
        if ($address === '') {
            throw InvalidPayloadException::required($payloadType, $field);
        }

        if (filter_var($address, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) === false) {
            throw InvalidPayloadException::invalidFormat($payloadType, $field);
        }
    }

    public function toQrString(): string
    {
        $query = [];

        if ($this->subject !== null && $this->subject !== '') {
            $query[] = 'subject='.rawurlencode($this->subject);
        }

        if ($this->body !== null && $this->body !== '') {
            $query[] = 'body='.rawurlencode($this->body);
        }

        return 'mailto:'.$this->to.($query === [] ? '' : '?'.implode('&', $query));
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
        return (string) $translator->get('qr::qr.descriptions.email');
    }
}
