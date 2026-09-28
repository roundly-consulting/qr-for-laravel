<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use RoundlyConsulting\Qr\Contracts\QrFactory;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\Segmentation;

/**
 * Passes when the value fits in a QR code of at most `$maxVersion` at the given level.
 *
 * The validation face of `Qr::fits()`: unset arguments take the configured defaults
 * (`qr.error_correction`, `qr.versions.max`, `qr.eci`, `qr.kanji`) — the settings `Qr::text()`
 * encodes with — so the rule never passes input the encoder then rejects. Explicit arguments
 * win.
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

        if (! app(QrFactory::class)->fits((string) $value, $this->errorCorrection, $this->maxVersion, $this->segmentation)) {
            $fail('qr::qr.validation.fits_in_qr_code')->translate();
        }
    }
}
