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
 * A vCard 3.0 contact (RFC 2426): CRLF line breaks, `\ , ;` and newlines escaped in text
 * values, the `URL` written as a URI (spaces and controls percent-encoded), no line folding.
 *
 * `$name` is the display name (`FN`). The structured name (`N`, family;given;additional;
 * prefixes;suffixes) comes from the optional name parts; with none given the name is never
 * guessed apart — the whole display name goes into the given-name component (`N:;Jana
 * Nováková;;;`), which contact apps show unchanged and file under its first letter.
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
        public ?string $familyName = null,
        public ?string $givenName = null,
        public ?string $additionalNames = null,
        public ?string $honorificPrefixes = null,
        public ?string $honorificSuffixes = null,
    ) {
        if (trim($name) === '') {
            throw InvalidPayloadException::required('VCard', 'name');
        }

        if (mb_strlen($name) > 255) {
            throw InvalidPayloadException::tooLong('VCard', 'name', 255);
        }

        foreach ($this->nameParts() as $field => $part) {
            if (mb_strlen($part) > 255) {
                throw InvalidPayloadException::tooLong('VCard', $field, 255);
            }
        }

        foreach ($emails as $email) {
            Email::assertAddress($email, 'VCard', 'emails');
        }

        $this->phones = array_map(static fn (string $phone): string => Phone::normalize($phone, 'VCard', 'phones'), $phones);
    }

    public function toQrString(): string
    {
        $parts = array_map(self::escape(...), $this->nameParts());
        $structured = array_filter($parts, static fn (string $part): bool => $part !== '') === []
            ? ';'.self::escape($this->name).';;;'
            : implode(';', $parts);
        $lines = ['BEGIN:VCARD', 'VERSION:3.0', 'N:'.$structured, 'FN:'.self::escape($this->name)];

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
            $lines[] = 'URL:'.self::uri($this->url);
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

    /**
     * The `N` components in order, trimmed ('' when unset).
     *
     * @return array{familyName: string, givenName: string, additionalNames: string, honorificPrefixes: string, honorificSuffixes: string}
     */
    private function nameParts(): array
    {
        return [
            'familyName' => trim((string) $this->familyName),
            'givenName' => trim((string) $this->givenName),
            'additionalNames' => trim((string) $this->additionalNames),
            'honorificPrefixes' => trim((string) $this->honorificPrefixes),
            'honorificSuffixes' => trim((string) $this->honorificSuffixes),
        ];
    }

    /**
     * `URL` has the URI value type (RFC 2426 §3.6.8), so it is not TEXT-escaped; spaces and
     * control characters — which a URI cannot hold and which would break the line format —
     * are percent-encoded.
     */
    private static function uri(string $value): string
    {
        return (string) preg_replace_callback('/[\x00-\x20\x7F]/', static fn (array $match): string => rawurlencode($match[0]), $value);
    }

    private static function escape(string $value): string
    {
        return str_replace(['\\', ',', ';', "\r\n", "\r", "\n"], ['\\\\', '\\,', '\;', '\\n', '\\n', '\\n'], $value);
    }
}
