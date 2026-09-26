<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Qr\Banking\Iban;
use RoundlyConsulting\Qr\Exceptions\InvalidIbanException;

/**
 * A string column holding an IBAN in its compact electronic form, exposed as {@see Iban}.
 * Assigning an invalid IBAN throws.
 *
 * @implements CastsAttributes<Iban|null, Iban|string|null>
 */
final class AsIban implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidIbanException
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Iban
    {
        return is_string($value) && $value !== '' ? Iban::fromString($value) : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidIbanException
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Iban) {
            return $value->electronic();
        }

        return Iban::fromString($value)->electronic();
    }
}
