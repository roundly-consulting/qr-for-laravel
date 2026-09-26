<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads;

use Illuminate\Contracts\Translation\Translator;
use RoundlyConsulting\Qr\Contracts\Payload;
use RoundlyConsulting\Qr\DataTransferObjects\PayloadRequirements;
use RoundlyConsulting\Qr\Enums\Sensitivity;

/**
 * Free text, encoded as given.
 *
 * A raw string that is really a 2FA seed or Wi-Fi credential (it starts, after leading
 * whitespace and in any case, with `otpauth:`, `otpauth-migration:` or `WIFI:`) is treated
 * as a secret, whichever entry point it came through; the two otpauth forms also lock the
 * sensitivity so it cannot be lowered.
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
        return new PayloadRequirements(locked: $this->isOtpauth() ? [PayloadRequirements::SENSITIVITY] : []);
    }

    public function sensitivity(): Sensitivity
    {
        return $this->isOtpauth() || $this->startsWith('wifi:') ? Sensitivity::Secret : Sensitivity::Public;
    }

    public function description(Translator $translator): string
    {
        $key = match (true) {
            $this->isOtpauth() => 'otpauth',
            $this->startsWith('wifi:') => 'wifi',
            default => 'text',
        };

        return (string) $translator->get('qr::qr.descriptions.'.$key);
    }

    private function isOtpauth(): bool
    {
        return $this->startsWith('otpauth:') || $this->startsWith('otpauth-migration:');
    }

    private function startsWith(string $prefix): bool
    {
        return str_starts_with(strtolower(ltrim($this->text)), $prefix);
    }
}
