<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Banking;

use RoundlyConsulting\Qr\Exceptions\InvalidIbanException;
use Stringable;

/**
 * A validated International Bank Account Number (ISO 13616): known country, exact length,
 * BBAN structure per the SWIFT IBAN Registry and ISO 7064 MOD 97-10 check digits.
 */
final readonly class Iban implements Stringable
{
    private function __construct(private string $value) {}

    /**
     * Spaces (incl. no-break spaces) and dashes are stripped and letters upper-cased.
     *
     * @throws InvalidIbanException
     */
    public static function fromString(string $value): self
    {
        $iban = strtoupper((string) preg_replace('/[\s\x{00A0}\-]+/u', '', $value));

        if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban) !== 1) {
            throw InvalidIbanException::format();
        }

        $country = substr($iban, 0, 2);

        if (! IbanRegistry::has($country)) {
            throw InvalidIbanException::country();
        }

        if (strlen($iban) !== IbanRegistry::length($country)) {
            throw InvalidIbanException::length();
        }

        if (preg_match((string) IbanRegistry::pattern($country), substr($iban, 4)) !== 1) {
            throw InvalidIbanException::format();
        }

        if (Mod97::remainder(substr($iban, 4).substr($iban, 0, 4)) !== 1) {
            throw InvalidIbanException::checksum();
        }

        return new self($iban);
    }

    public static function isValid(string $value): bool
    {
        try {
            self::fromString($value);

            return true;
        } catch (InvalidIbanException) {
            return false;
        }
    }

    public function country(): string
    {
        return substr($this->value, 0, 2);
    }

    public function checkDigits(): string
    {
        return substr($this->value, 2, 2);
    }

    public function bban(): string
    {
        return substr($this->value, 4);
    }

    /**
     * The compact form: `SK9611000000002918599669`.
     */
    public function electronic(): string
    {
        return $this->value;
    }

    /**
     * The print form in groups of four: `SK96 1100 0000 0029 1859 9669`.
     */
    public function formatted(): string
    {
        return implode(' ', str_split($this->value, 4));
    }

    public function isEea(): bool
    {
        return Eea::contains($this->country());
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
