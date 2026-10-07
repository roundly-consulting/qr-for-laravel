<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads\Payments\Epc;

use Illuminate\Contracts\Translation\Translator;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Qr\Banking\Bic;
use RoundlyConsulting\Qr\Banking\CreditorReference;
use RoundlyConsulting\Qr\Banking\Iban;
use RoundlyConsulting\Qr\Contracts\Payload;
use RoundlyConsulting\Qr\DataTransferObjects\PayloadRequirements;
use RoundlyConsulting\Qr\Enums\EciMode;
use RoundlyConsulting\Qr\Enums\EpcCharset;
use RoundlyConsulting\Qr\Enums\EpcVersion;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\Segmentation;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;
use RoundlyConsulting\Qr\Exceptions\PaymentPayloadTooLongException;
use RoundlyConsulting\Qr\Exceptions\UnsupportedCurrencyException;
use RoundlyConsulting\Qr\Support\Amounts;
use RoundlyConsulting\Qr\Support\TextNormalizer;

/**
 * A SEPA credit transfer code per EPC069-12 v3.1 ("GiroCode"): LF-separated elements, the
 * last populated element without a trailing separator, at most 331 bytes in the declared
 * character set, error correction M and at most version 13 (both fixed by the standard).
 */
final readonly class EpcPayment implements Payload
{
    public const int MAX_BYTES = 331;

    public const int MAX_VERSION = 13;

    private const string TYPE = 'EPC';

    private const string SEPA_LATIN = "/^[A-Za-z0-9\/\-?:().,'+ ]*$/";

    public string $name;

    public Iban $iban;

    public ?Bic $bic;

    public ?CreditorReference $reference;

    public ?string $structuredReference;

    public ?string $text;

    public ?string $information;

    /**
     * @throws InvalidPayloadException
     */
    public function __construct(
        string $name,
        string|Iban $iban,
        string|Bic|null $bic = null,
        public ?Money $amount = null,
        public ?string $purpose = null,
        string|CreditorReference|null $reference = null,
        ?string $text = null,
        ?string $information = null,
        public ?EpcVersion $version = null,
        public ?EpcCharset $charset = null,
        public ?bool $strictCharset = null,
    ) {
        $this->name = self::text($name, 'name', 70, required: true) ?? '';
        $this->iban = $iban instanceof Iban ? $iban : Iban::fromString($iban);
        $this->bic = match (true) {
            $bic instanceof Bic => $bic,
            $bic === null || trim($bic) === '' => null,
            default => Bic::fromString($bic),
        };
        $this->text = self::text($text, 'text', 140);
        $this->information = self::text($information, 'information', 70);

        if ($amount !== null) {
            if (! Amounts::isIsoCurrency($amount)) {
                throw UnsupportedCurrencyException::notIso(self::TYPE);
            }

            if (Amounts::currencyCode($amount) !== 'EUR') {
                throw UnsupportedCurrencyException::notEuro(self::TYPE);
            }

            if (! Amounts::fitsEpcRange($amount)) {
                throw InvalidPayloadException::outOfRange(self::TYPE, 'amount');
            }
        }

        if ($purpose !== null && preg_match('/^[A-Z0-9]{1,4}\z/', $purpose) !== 1) {
            throw InvalidPayloadException::invalidFormat(self::TYPE, 'purpose');
        }

        [$this->reference, $this->structuredReference] = self::reference($reference);

        if ($this->structuredReference !== null && $this->text !== null) {
            throw InvalidPayloadException::mutuallyExclusive(self::TYPE, 'text', 'reference');
        }
    }

    /**
     * Fill the fields left null with defaults (the package configuration, via the manager).
     */
    public function withDefaults(EpcVersion $version, EpcCharset $charset, bool $strictCharset): self
    {
        return new self(
            $this->name,
            $this->iban,
            $this->bic,
            $this->amount,
            $this->purpose,
            $this->reference ?? $this->structuredReference,
            $this->text,
            $this->information,
            $this->version ?? $version,
            $this->charset ?? $charset,
            $this->strictCharset ?? $strictCharset,
        );
    }

    /**
     * Parse an EPC payload (LF or CRLF separators) back into a payment.
     *
     * @throws InvalidPayloadException
     */
    public static function fromString(string $payload): self
    {
        if (strlen($payload) > self::MAX_BYTES) {
            throw PaymentPayloadTooLongException::bytes(self::TYPE, strlen($payload), self::MAX_BYTES);
        }

        $lines = explode("\n", str_replace("\r\n", "\n", $payload));

        if (count($lines) < 7 || count($lines) > 12 || $lines[0] !== 'BCD' || $lines[3] !== 'SCT') {
            throw InvalidPayloadException::invalidFormat(self::TYPE, 'payload');
        }

        $version = EpcVersion::tryFrom($lines[1]) ?? throw InvalidPayloadException::invalidFormat(self::TYPE, 'version');
        $charset = (preg_match('/^[1-8]$/', $lines[2]) === 1 ? EpcCharset::tryFromCode((int) $lines[2]) : null)
            ?? throw InvalidPayloadException::invalidFormat(self::TYPE, 'charset');

        $lines = array_pad($lines, 12, '');

        if ($charset !== EpcCharset::Utf8) {
            $lines = array_map(static fn (string $line): string => (string) mb_convert_encoding($line, 'UTF-8', $charset->mbEncoding()), $lines);
        } elseif (! mb_check_encoding(implode("\n", $lines), 'UTF-8')) {
            throw InvalidPayloadException::invalidFormat(self::TYPE, 'charset');
        }

        $amount = null;

        if ($lines[7] !== '') {
            if (preg_match('/^EUR([0-9]{1,9}(\.[0-9]{1,2})?)$/', $lines[7], $match) !== 1) {
                throw InvalidPayloadException::invalidFormat(self::TYPE, 'amount');
            }

            $amount = Amounts::fromDecimal($match[1], 'EUR');
        }

        return new self(
            name: $lines[5],
            iban: $lines[6],
            bic: $lines[4] === '' ? null : $lines[4],
            amount: $amount,
            purpose: $lines[8] === '' ? null : $lines[8],
            reference: $lines[9] === '' ? null : $lines[9],
            text: $lines[10] === '' ? null : $lines[10],
            information: $lines[11] === '' ? null : $lines[11],
            version: $version,
            charset: $charset,
        );
    }

    /**
     * The EPC payload. Reads no configuration: a null version/charset/strictCharset falls
     * back to the standard's defaults (002, UTF-8, off). `Qr::epc()` and the other manager
     * entry points fill them from `config('qr.payments.epc.*')` first.
     *
     * @throws InvalidPayloadException
     */
    public function toQrString(): string
    {
        $version = $this->version ?? EpcVersion::V002;
        $charset = $this->charset ?? EpcCharset::Utf8;

        if ($this->bic === null && $version->bicRequiredFor($this->iban)) {
            throw InvalidPayloadException::required(self::TYPE, 'bic');
        }

        if ($this->strictCharset === true) {
            foreach (['name' => $this->name, 'text' => $this->text, 'information' => $this->information, 'reference' => $this->structuredReference] as $field => $value) {
                if ($value !== null && preg_match(self::SEPA_LATIN, $value) !== 1) {
                    throw InvalidPayloadException::unrepresentable(self::TYPE, $field, 'the SEPA Latin character set');
                }
            }
        }

        $elements = [
            'BCD',
            $version->value,
            (string) $charset->code(),
            'SCT',
            $this->bic?->value() ?? '',
            $this->name,
            $this->iban->electronic(),
            $this->amount === null ? '' : 'EUR'.Amounts::decimal($this->amount),
            $this->purpose ?? '',
            $this->structuredReference ?? '',
            $this->text ?? '',
            $this->information ?? '',
        ];

        while (end($elements) === '') {
            array_pop($elements);
        }

        $payload = implode("\n", $elements);

        if ($charset !== EpcCharset::Utf8) {
            $encoded = mb_convert_encoding($payload, $charset->mbEncoding(), 'UTF-8');

            if (mb_convert_encoding($encoded, 'UTF-8', $charset->mbEncoding()) !== $payload) {
                throw InvalidPayloadException::unrepresentable(self::TYPE, self::firstUnrepresentable($elements, $charset), $charset->mbEncoding());
            }

            $payload = $encoded;
        }

        if (strlen($payload) > self::MAX_BYTES) {
            throw PaymentPayloadTooLongException::bytes(self::TYPE, strlen($payload), self::MAX_BYTES);
        }

        return $payload;
    }

    public function requirements(): PayloadRequirements
    {
        return new PayloadRequirements(
            errorCorrection: ErrorCorrection::Medium,
            maxVersion: self::MAX_VERSION,
            segmentation: Segmentation::Byte,
            eci: EciMode::Never,
            boostErrorCorrection: false,
            locked: [
                PayloadRequirements::ERROR_CORRECTION,
                PayloadRequirements::ECI,
                PayloadRequirements::SEGMENTATION,
                PayloadRequirements::BOOST_ERROR_CORRECTION,
            ],
        );
    }

    public function sensitivity(): Sensitivity
    {
        return Sensitivity::Personal;
    }

    public function description(Translator $translator): string
    {
        return (string) $translator->get('qr::qr.descriptions.epc');
    }

    /**
     * @throws InvalidPayloadException
     */
    private static function text(?string $value, string $field, int $max, bool $required = false): ?string
    {
        $value = $value === null ? null : trim(TextNormalizer::clean($value));

        if ($value === null || $value === '') {
            return $required ? throw InvalidPayloadException::required(self::TYPE, $field) : null;
        }

        if (mb_strlen($value) > $max) {
            throw InvalidPayloadException::tooLong(self::TYPE, $field, $max);
        }

        return $value;
    }

    /**
     * An RF reference is validated as ISO 11649; any other structured reference is kept as
     * given (1–35 characters).
     *
     * @return array{0: ?CreditorReference, 1: ?string}
     *
     * @throws InvalidPayloadException
     */
    private static function reference(string|CreditorReference|null $reference): array
    {
        if ($reference instanceof CreditorReference) {
            return [$reference, $reference->electronic()];
        }

        $reference = $reference === null ? null : trim(TextNormalizer::clean($reference));

        if ($reference === null || $reference === '') {
            return [null, null];
        }

        if (mb_strlen($reference) > 35) {
            throw InvalidPayloadException::tooLong(self::TYPE, 'reference', 35);
        }

        if (str_starts_with(strtoupper($reference), 'RF')) {
            $creditor = CreditorReference::fromString($reference);

            return [$creditor, $creditor->electronic()];
        }

        return [null, $reference];
    }

    /**
     * @param  list<string>  $elements
     */
    private static function firstUnrepresentable(array $elements, EpcCharset $charset): string
    {
        $fields = ['service', 'version', 'charset', 'identification', 'bic', 'name', 'iban', 'amount', 'purpose', 'reference', 'text', 'information'];

        foreach ($elements as $index => $element) {
            if (mb_convert_encoding(mb_convert_encoding($element, $charset->mbEncoding(), 'UTF-8'), 'UTF-8', $charset->mbEncoding()) !== $element) {
                return $fields[$index];
            }
        }

        return 'payload';
    }
}
