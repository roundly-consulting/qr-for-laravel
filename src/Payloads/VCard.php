<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads;

use Illuminate\Contracts\Translation\Translator;
use RoundlyConsulting\Qr\Contracts\Payload;
use RoundlyConsulting\Qr\DataTransferObjects\PayloadRequirements;
use RoundlyConsulting\Qr\Enums\Segmentation;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;

/**
 * A vCard 3.0 contact (RFC 2426): CRLF line breaks, `\ , ;` and newlines escaped, no line
 * folding. The whole name goes into the family-name component of `N` and into `FN`.
 */
final readonly class VCard implements Payload
{
    /** @var list<string> */
    public array $phones;

    /**
     * @param  list<string>  $phones
     * @param  list<string>  $emails
     *
     * @throws InvalidPayloadException
     */
    public function __construct(
        public string $name,
        public ?string $organization = null,
        public ?string $title = null,
        array $phones = [],
        public array $emails = [],
        public ?string $url = null,
        public ?string $address = null,
        public ?string $note = null,
    ) {
        if (trim($name) === '') {
            throw InvalidPayloadException::required('VCard', 'name');
        }

        if (mb_strlen($name) > 255) {
            throw InvalidPayloadException::tooLong('VCard', 'name', 255);
        }

        foreach ($emails as $email) {
            Email::assertAddress($email, 'VCard', 'emails');
        }

        $this->phones = array_map(static fn (string $phone): string => Phone::normalize($phone, 'VCard', 'phones'), $phones);
    }

    public function toQrString(): string
    {
        $name = self::escape($this->name);
        $lines = ['BEGIN:VCARD', 'VERSION:3.0', 'N:'.$name.';;;;', 'FN:'.$name];

        foreach (['ORG' => $this->organization, 'TITLE' => $this->title] as $property => $value) {
            if ($value !== null && $value !== '') {
                $lines[] = $property.':'.self::escape($value);
            }
        }

        foreach ($this->phones as $phone) {
            $lines[] = 'TEL:'.$phone;
        }

        foreach ($this->emails as $email) {
            $lines[] = 'EMAIL:'.self::escape($email);
        }

        if ($this->url !== null && $this->url !== '') {
            $lines[] = 'URL:'.self::escape($this->url);
        }

        if ($this->address !== null && $this->address !== '') {
            $lines[] = 'ADR:;;'.self::escape($this->address).';;;;';
        }

        if ($this->note !== null && $this->note !== '') {
            $lines[] = 'NOTE:'.self::escape($this->note);
        }

        $lines[] = 'END:VCARD';

        return implode("\r\n", $lines);
    }

    public function requirements(): PayloadRequirements
    {
        return new PayloadRequirements(segmentation: Segmentation::Byte);
    }

    public function sensitivity(): Sensitivity
    {
        return Sensitivity::Personal;
    }

    public function description(Translator $translator): string
    {
        return (string) $translator->get('qr::qr.descriptions.vcard');
    }

    private static function escape(string $value): string
    {
        return str_replace(['\\', ',', ';', "\r\n", "\r", "\n"], ['\\\\', '\\,', '\;', '\\n', '\\n', '\\n'], $value);
    }
}
