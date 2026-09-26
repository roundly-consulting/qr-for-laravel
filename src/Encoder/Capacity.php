<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Encoder;

use RoundlyConsulting\Qr\Enums\ErrorCorrection;

/**
 * Symbol capacity per version and error-correction level (ISO/IEC 18004:2015 §7.5.1,
 * Table 9). Index 0 of every row is unused so a version indexes its own column.
 *
 * @internal
 */
final class Capacity
{
    public const int MIN_VERSION = 1;

    public const int MAX_VERSION = 40;

    /** @var array<int, list<int>> error-correction codewords per block, by ECC ordinal */
    private const array ECC_CODEWORDS_PER_BLOCK = [
        [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
        [-1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        [-1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    ];

    /** @var array<int, list<int>> number of error-correction blocks, by ECC ordinal */
    private const array NUM_BLOCKS = [
        [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
        [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
        [-1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
        [-1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81],
    ];

    public static function size(int $version): int
    {
        return $version * 4 + 17;
    }

    public static function eccCodewordsPerBlock(int $version, ErrorCorrection $ecc): int
    {
        return self::ECC_CODEWORDS_PER_BLOCK[$ecc->ordinal()][$version];
    }

    public static function numBlocks(int $version, ErrorCorrection $ecc): int
    {
        return self::NUM_BLOCKS[$ecc->ordinal()][$version];
    }

    /**
     * Modules available for data and error-correction codewords (incl. remainder bits):
     * the symbol area minus finder, separator, timing, alignment, format and version areas.
     */
    public static function rawDataModules(int $version): int
    {
        $modules = (16 * $version + 128) * $version + 64;

        if ($version >= 2) {
            $alignment = intdiv($version, 7) + 2;
            $modules -= (25 * $alignment - 10) * $alignment - 55;

            if ($version >= 7) {
                $modules -= 36;
            }
        }

        return $modules;
    }

    public static function totalCodewords(int $version): int
    {
        return intdiv(self::rawDataModules($version), 8);
    }

    public static function remainderBits(int $version): int
    {
        return self::rawDataModules($version) % 8;
    }

    public static function dataCodewords(int $version, ErrorCorrection $ecc): int
    {
        return self::totalCodewords($version) - self::eccCodewordsPerBlock($version, $ecc) * self::numBlocks($version, $ecc);
    }

    public static function dataBits(int $version, ErrorCorrection $ecc): int
    {
        return self::dataCodewords($version, $ecc) * 8;
    }
}
