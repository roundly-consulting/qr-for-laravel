<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Banking;

use RoundlyConsulting\Qr\Exceptions\InvalidCreditorReferenceException;
use Stringable;

/**
 * An ISO 11649 structured creditor reference: `RF`, two check digits and 1–21
 * alphanumerics, valid when the MOD 97-10 remainder of reference + `RF` + check digits is 1.
 */
final readonly class CreditorReference implements Stringable
{
    private function __construct(private string $value) {}

    /**
     * @throws InvalidCreditorReferenceException
     */
    public static function fromString(string $value): self
    {
        $reference = strtoupper((string) preg_replace('/\s+/', '', $value));

        if (preg_match('/^RF[0-9]{2}[A-Z0-9]{1,21}$/', $reference) !== 1) {
            throw InvalidCreditorReferenceException::format();
        }

        if (Mod97::remainder(substr($reference, 4).substr($reference, 0, 4)) !== 1) {
            throw InvalidCreditorReferenceException::checksum();
        }

        return new self($reference);
    }

    /**
     * Compute the check digits for a reference (`539007547034` → `RF18539007547034`).
     *
     * @throws InvalidCreditorReferenceException
     */
    public static function generate(string $reference): self
    {
        $reference = strtoupper((string) preg_replace('/\s+/', '', $reference));

        if (preg_match('/^[A-Z0-9]{1,21}$/', $reference) !== 1) {
            throw InvalidCreditorReferenceException::format();
        }

        $check = 98 - Mod97::remainder($reference.'RF00');

        return new self('RF'.str_pad((string) $check, 2, '0', STR_PAD_LEFT).$reference);
    }

    public static function isValid(string $value): bool
    {
        try {
            self::fromString($value);

            return true;
        } catch (InvalidCreditorReferenceException) {
            return false;
        }
    }

    public function electronic(): string
    {
        return $this->value;
    }

    public function formatted(): string
    {
        return implode(' ', str_split($this->value, 4));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
