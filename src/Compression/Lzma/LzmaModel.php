<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Compression\Lzma;

/**
 * The adaptive probability model and state machine shared by the LZMA decoder and encoder
 * (LZMA specification: "The code of decoder"). All probabilities live in one flat array;
 * the constants are offsets into it. For lc + lp = 3 the literal coder needs 0x300 × 8
 * probabilities.
 *
 * @internal
 */
final class LzmaModel
{
    public const int NUM_STATES = 12;

    public const int POS_BITS_MAX = 4;

    public const int PROB_INIT = 1024;

    public const int MATCH_MIN_LEN = 2;

    public const int MATCH_MAX_LEN = 273;

    public const int END_POS_MODEL_INDEX = 14;

    public const int NUM_LEN_TO_POS_STATES = 4;

    public const int ALIGN_BITS = 4;

    public const int END_MARKER_DISTANCE = 0xFFFFFFFF;

    public const int IS_MATCH = 0;

    public const int IS_REP = 192;

    public const int IS_REP_G0 = 204;

    public const int IS_REP_G1 = 216;

    public const int IS_REP_G2 = 228;

    public const int IS_REP0_LONG = 240;

    public const int POS_SLOT = 432;

    public const int POS_SPECIAL = 688;

    public const int ALIGN = 803;

    /** A length coder: choice, choice2, low[16][8], mid[16][8], high[256] — 514 probabilities. */
    public const int LEN = 819;

    public const int REP_LEN = 1333;

    public const int LITERAL = 1847;

    public const int LEN_CHOICE = 0;

    public const int LEN_CHOICE2 = 1;

    public const int LEN_LOW = 2;

    public const int LEN_MID = 130;

    public const int LEN_HIGH = 258;

    /**
     * @return list<int>
     */
    public static function probabilities(LzmaProperties $properties): array
    {
        return array_fill(0, self::LITERAL + (0x300 << ($properties->lc + $properties->lp)), self::PROB_INIT);
    }

    public static function afterLiteral(int $state): int
    {
        return $state < 4 ? 0 : ($state < 10 ? $state - 3 : $state - 6);
    }

    public static function afterMatch(int $state): int
    {
        return $state < 7 ? 7 : 10;
    }

    public static function afterRep(int $state): int
    {
        return $state < 7 ? 8 : 11;
    }

    public static function afterShortRep(int $state): int
    {
        return $state < 7 ? 9 : 11;
    }

    /**
     * Distance slot: the distance itself below 4, otherwise twice the index of its highest
     * set bit plus the bit below it.
     */
    public static function positionSlot(int $distance): int
    {
        if ($distance < 4) {
            return $distance;
        }

        $bits = 31 - self::leadingZeros($distance);

        return 2 * $bits + (($distance >> ($bits - 1)) & 1);
    }

    public static function literalOffset(LzmaProperties $properties, int $position, int $previousByte): int
    {
        $state = (($position & ((1 << $properties->lp) - 1)) << $properties->lc) + ($previousByte >> (8 - $properties->lc));

        return self::LITERAL + 0x300 * $state;
    }

    private static function leadingZeros(int $value): int
    {
        $zeros = 0;

        for ($bit = 31; $bit >= 0 && (($value >> $bit) & 1) === 0; $bit--) {
            $zeros++;
        }

        return $zeros;
    }
}
