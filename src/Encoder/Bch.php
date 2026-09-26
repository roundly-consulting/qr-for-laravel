<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Encoder;

use RoundlyConsulting\Qr\Enums\ErrorCorrection;

/**
 * BCH-protected format and version information (ISO/IEC 18004:2015 §7.9 and §7.10).
 *
 * @internal
 */
final class Bch
{
    /** Format information generator x^10 + x^8 + x^5 + x^4 + x^2 + x + 1. */
    public const int FORMAT_GENERATOR = 0x537;

    /** XOR mask applied so the format information is never all zero. */
    public const int FORMAT_MASK = 0x5412;

    /** Version information generator x^12 + x^11 + x^10 + x^9 + x^8 + x^5 + x^2 + 1. */
    public const int VERSION_GENERATOR = 0x1F25;

    /**
     * The 15-bit masked format information for an error-correction level and mask.
     */
    public static function formatBits(ErrorCorrection $ecc, int $mask): int
    {
        $data = ($ecc->formatBits() << 3) | $mask;

        return (($data << 10) | self::remainder($data, 10, self::FORMAT_GENERATOR)) ^ self::FORMAT_MASK;
    }

    /**
     * The 18-bit version information (versions 7..40).
     */
    public static function versionBits(int $version): int
    {
        return ($version << 12) | self::remainder($version, 12, self::VERSION_GENERATOR);
    }

    public static function hammingDistance(int $a, int $b): int
    {
        return substr_count(decbin($a ^ $b), '1');
    }

    /**
     * Polynomial division remainder of data·x^degree by the generator over GF(2).
     */
    private static function remainder(int $data, int $degree, int $generator): int
    {
        $remainder = $data << $degree;

        for ($bit = 31; $bit >= $degree; $bit--) {
            if (($remainder >> $bit) & 1) {
                $remainder ^= $generator << ($bit - $degree);
            }
        }

        return $remainder;
    }
}
