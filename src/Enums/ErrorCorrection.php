<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Enums;

use RoundlyConsulting\Enums\Helpers;
use RoundlyConsulting\Qr\Exceptions\InvalidQrConfigException;

/**
 * The four ISO/IEC 18004:2015 error-correction levels (§7.5.1).
 */
enum ErrorCorrection: string
{
    use Helpers;

    case Low = 'L';
    case Medium = 'M';
    case Quartile = 'Q';
    case High = 'H';

    /**
     * The two-bit indicator carried in the format information (ISO §7.9.1, Table 12).
     */
    public function formatBits(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 0,
            self::Quartile => 3,
            self::High => 2,
        };
    }

    /**
     * Row index into the error-correction tables (L, M, Q, H).
     */
    public function ordinal(): int
    {
        return match ($this) {
            self::Low => 0,
            self::Medium => 1,
            self::Quartile => 2,
            self::High => 3,
        };
    }

    /**
     * Approximate share of codewords that can be restored.
     */
    public function recoveryPercent(): int
    {
        return match ($this) {
            self::Low => 7,
            self::Medium => 15,
            self::Quartile => 25,
            self::High => 30,
        };
    }

    /**
     * The next stronger level, or null for H.
     */
    public function stronger(): ?self
    {
        return match ($this) {
            self::Low => self::Medium,
            self::Medium => self::Quartile,
            self::Quartile => self::High,
            self::High => null,
        };
    }

    /**
     * Lenient parse of a letter (L/M/Q/H) or case name (low/medium/quartile/high), any case.
     */
    public static function tryFromInput(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = strtoupper(trim($value));

        return self::tryFrom($normalized) ?? match ($normalized) {
            'LOW' => self::Low,
            'MEDIUM' => self::Medium,
            'QUARTILE' => self::Quartile,
            'HIGH' => self::High,
            default => null,
        };
    }

    /**
     * @throws InvalidQrConfigException
     */
    public static function fromConfig(mixed $value, string $key = 'qr.error_correction'): self
    {
        return self::tryFromInput($value) ?? throw InvalidQrConfigException::invalid($key, 'expected one of L, M, Q, H');
    }
}
