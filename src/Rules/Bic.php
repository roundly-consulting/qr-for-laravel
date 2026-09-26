<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use RoundlyConsulting\Qr\Banking\Bic as BicValue;

final readonly class Bic implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! BicValue::isValid($value)) {
            $fail('qr::qr.validation.bic')->translate();
        }
    }
}
