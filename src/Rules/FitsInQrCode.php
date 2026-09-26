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

/**
 * Passes when the value fits in a QR code of at most `$maxVersion` at the given level.
 */
final readonly class FitsInQrCode implements ValidationRule
{
    public function __construct(
        private ErrorCorrection $errorCorrection = ErrorCorrection::Medium,
        private int $maxVersion = 40,
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
                errorCorrection: $this->errorCorrection,
                maxVersion: $this->maxVersion,
                mask: 0,
                boostErrorCorrection: false,
                segmentation: $this->segmentation,
            ));
        } catch (DataTooLongException) {
            $fail('qr::qr.validation.fits_in_qr_code')->translate();
        }
    }
}
