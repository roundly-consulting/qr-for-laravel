<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use RoundlyConsulting\Qr\DataTransferObjects\EncodeOptions;
use RoundlyConsulting\Qr\Encoder\Encoder;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\Segmentation;
use RoundlyConsulting\Qr\Exceptions\DataTooLongException;
use RoundlyConsulting\Qr\Support\ConfigGuard;

/**
 * Passes when the value fits in a QR code of at most `$maxVersion` at the given level.
 *
 * Unset arguments take the configured defaults (`qr.error_correction`, `qr.versions.max`,
 * `qr.eci`, `qr.kanji`) — the settings `Qr::text()` encodes with — so the rule never passes
 * input the encoder then rejects. Explicit arguments win.
 */
final readonly class FitsInQrCode implements ValidationRule
{
    public function __construct(
        private ?ErrorCorrection $errorCorrection = null,
        private ?int $maxVersion = null,
        private Segmentation $segmentation = Segmentation::Optimal,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            $fail('qr::qr.validation.fits_in_qr_code')->translate();

            return;
        }

        try {
            (new Encoder)->encode((string) $value, new EncodeOptions(
                errorCorrection: $this->errorCorrection ?? ConfigGuard::errorCorrection(),
                maxVersion: $this->maxVersion ?? ConfigGuard::maxVersion(),
                mask: 0,
                boostErrorCorrection: false,
                eci: ConfigGuard::eci(),
                segmentation: $this->segmentation,
                kanji: ConfigGuard::kanji(),
            ));
        } catch (DataTooLongException) {
            $fail('qr::qr.validation.fits_in_qr_code')->translate();
        }
    }
}
