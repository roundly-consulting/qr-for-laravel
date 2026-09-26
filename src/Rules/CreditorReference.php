<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use RoundlyConsulting\Qr\Banking\CreditorReference as CreditorReferenceValue;

final readonly class CreditorReference implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! CreditorReferenceValue::isValid($value)) {
            $fail('qr::qr.validation.creditor_reference')->translate();
        }
    }
}
