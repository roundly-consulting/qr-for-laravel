<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Banking;

use RoundlyConsulting\Qr\Exceptions\InvalidBicException;
use Stringable;

/**
 * A Business Identifier Code (ISO 9362): 4 letters institution, 2 letters country,
 * 2 alphanumerics location and an optional 3-alphanumeric branch.
 */
final readonly class Bic implements Stringable
{
    private function __construct(private string $value) {}

    /**
     * @throws InvalidBicException
     */
    public static function fromString(string $value): self
    {
        $bic = strtoupper((string) preg_replace('/\s+/', '', $value));

        if (preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $bic) !== 1) {
            throw InvalidBicException::format();
        }

        if (! CountryCodes::exists(substr($bic, 4, 2))) {
            throw InvalidBicException::country();
        }

        return new self($bic);
    }

    public static function isValid(string $value): bool
    {
        try {
            self::fromString($value);

            return true;
        } catch (InvalidBicException) {
            return false;
        }
    }

    public function institution(): string
    {
        return substr($this->value, 0, 4);
    }

    public function country(): string
    {
        return substr($this->value, 4, 2);
    }

    public function location(): string
    {
        return substr($this->value, 6, 2);
    }

    public function branch(): ?string
    {
        return strlen($this->value) === 11 ? substr($this->value, 8, 3) : null;
    }

    /**
     * A '0' as the second location character marks a test BIC.
     */
    public function isTestCode(): bool
    {
        return $this->value[7] === '0';
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
