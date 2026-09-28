<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads;

use Illuminate\Contracts\Translation\Translator;
use RoundlyConsulting\Qr\Contracts\Payload;
use RoundlyConsulting\Qr\DataTransferObjects\PayloadRequirements;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Support\SecretContent;

/**
 * Free text, encoded as given.
 *
 * A raw string that is really a 2FA seed or Wi-Fi credential (see {@see SecretContent})
 * is treated as a secret, whichever entry point it came through; the two otpauth forms also
 * lock the sensitivity so it cannot be lowered.
 */
final readonly class Text implements Payload
{
    public function __construct(public string $text) {}

    public function toQrString(): string
    {
        return $this->text;
    }

    public function requirements(): PayloadRequirements
    {
        return new PayloadRequirements(locked: SecretContent::isOtpauth($this->text) ? [PayloadRequirements::SENSITIVITY] : []);
    }

    public function sensitivity(): Sensitivity
    {
        return SecretContent::isSecret($this->text) ? Sensitivity::Secret : Sensitivity::Public;
    }

    public function description(Translator $translator): string
    {
        $key = match (true) {
            SecretContent::isOtpauth($this->text) => 'otpauth',
            SecretContent::isWifi($this->text) => 'wifi',
            default => 'text',
        };

        return (string) $translator->get('qr::qr.descriptions.'.$key);
    }
}
