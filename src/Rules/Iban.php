<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use RoundlyConsulting\Qr\Banking\Iban as IbanValue;
use RoundlyConsulting\Qr\Exceptions\InvalidIbanException;

/**
 * `'iban' => ['required', new Iban]` — optionally restricted to EEA countries.
 */
final readonly class Iban implements ValidationRule
{
    public function __construct(private bool $eeaOnly = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $iban = IbanValue::fromString(is_string($value) ? $value : '');
        } catch (InvalidIbanException) {
            $fail('qr::qr.validation.iban')->translate();

            return;
        }

        if ($this->eeaOnly && ! $iban->isEea()) {
            $fail('qr::qr.validation.iban_eea')->translate();
        }
    }
}
