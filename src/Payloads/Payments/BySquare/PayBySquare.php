<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads\Payments\BySquare;

use Illuminate\Contracts\Translation\Translator;
use RoundlyConsulting\Qr\Contracts\Payload;
use RoundlyConsulting\Qr\DataTransferObjects\PayloadRequirements;
use RoundlyConsulting\Qr\Enums\BySquare\BySquareVersion;
use RoundlyConsulting\Qr\Enums\EciMode;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\Segmentation;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Exceptions\BySquareDecodeException;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;

/**
 * A PAY by square payment document (by square specification 1.0.0 / 1.1.0 / 1.2.0): one or
 * more payments, the first being the preferred one. The code is a base32hex string —
 * one alphanumeric QR segment.
 */
final readonly class PayBySquare implements Payload
{
    public const string TYPE = 'PAY by square';

    public ?string $invoiceId;

    /**
     * @param  list<Payment>  $payments  first = preferred; at least one
     *
     * @throws InvalidPayloadException
     */
    public function __construct(
        public array $payments,
        ?string $invoiceId = null,
        public ?BySquareVersion $version = null,
        public ?bool $deburr = null,
    ) {
        if ($payments === []) {
            throw InvalidPayloadException::required(self::TYPE, 'payments');
        }

        $this->invoiceId = Payment::text($invoiceId, 'invoiceId', 10);
    }

    /**
     * Fill unset fields with defaults (the package configuration, via the manager).
     */
    public function withDefaults(BySquareVersion $version, bool $deburr): self
    {
        return new self($this->payments, $this->invoiceId, $this->version ?? $version, $this->deburr ?? $deburr);
    }

    /**
     * The PAY by square string. Reads no configuration: a null `version`/`deburr` falls back
     * to the standard's defaults (1.2.0, deburr on). `Qr::payBySquare()` and the other manager
     * entry points fill them from `config('qr.payments.bysquare.*')` first.
     *
     * @throws InvalidPayloadException
     */
    public function encode(): string
    {
        $version = $this->version ?? BySquareVersion::V1_2_0;
        $deburr = $this->deburr ?? true;

        foreach ($this->payments as $payment) {
            if ($payment->beneficiary !== null && ! $version->hasBeneficiaryBlock()) {
                throw InvalidPayloadException::unsupportedInVersion(self::TYPE, 'beneficiary', $version->semver());
            }

            if ($payment->beneficiary === null && $version->requiresBeneficiaryName()) {
                throw InvalidPayloadException::required(self::TYPE, 'beneficiary.name');
            }

            // Deburring can lengthen text ("ß" → "ss"), so the limits are checked again.
            if ($deburr) {
                self::recheck($payment->note, 'note', 140);
                self::recheck($payment->beneficiary?->name, 'beneficiary.name', Beneficiary::MAX_LENGTH);
                self::recheck($payment->beneficiary?->street, 'beneficiary.street', Beneficiary::MAX_LENGTH);
                self::recheck($payment->beneficiary?->city, 'beneficiary.city', Beneficiary::MAX_LENGTH);
            }
        }

        return BySquareCodec::encode(BySquareSerializer::serialize($this, $version, $deburr), $version);
    }

    /**
     * Parse a PAY by square string back into a document.
     *
     * @throws BySquareDecodeException
     */
    public static function decode(string $bySquare): self
    {
        $frame = BySquareCodec::decode($bySquare);

        return BySquareSerializer::unserialize($frame->serialized, $frame->version);
    }

    /**
     * The serialised, uncompressed data model (tab-separated).
     */
    public function serialize(): string
    {
        return BySquareSerializer::serialize($this, $this->version ?? BySquareVersion::V1_2_0, $this->deburr ?? true);
    }

    public function toQrString(): string
    {
        return $this->encode();
    }

    public function requirements(): PayloadRequirements
    {
        return new PayloadRequirements(
            errorCorrection: ErrorCorrection::Medium,
            segmentation: Segmentation::Single,
            eci: EciMode::Never,
            locked: [PayloadRequirements::ECI, PayloadRequirements::SEGMENTATION],
        );
    }

    public function sensitivity(): Sensitivity
    {
        return Sensitivity::Personal;
    }

    public function description(Translator $translator): string
    {
        return (string) $translator->get('qr::qr.descriptions.bysquare');
    }

    private static function recheck(?string $value, string $field, int $max): void
    {
        if ($value !== null && mb_strlen(Deburr::apply($value)) > $max) {
            throw InvalidPayloadException::tooLong(self::TYPE, $field, $max);
        }
    }
}
