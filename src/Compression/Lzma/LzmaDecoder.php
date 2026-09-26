<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Compression\Lzma;

use RoundlyConsulting\Qr\Exceptions\CorruptLzmaStreamException;

/**
 * Raw LZMA1 decoder (LZMA specification: "The code of decoder") for a stream of known
 * uncompressed length. Decoding stops exactly at that length, whether or not an
 * end-of-payload marker follows; a marker that arrives earlier, a distance beyond the
 * produced output or the dictionary, output beyond the declared length and a truncated
 * input are all rejected.
 *
 * @internal
 */
final class LzmaDecoder
{
    /**
     * @throws CorruptLzmaStreamException
     */
    public static function decode(string $stream, int $length, LzmaProperties $properties = new LzmaProperties): string
    {
        if ($length < 0) {
            throw CorruptLzmaStreamException::because('the declared length is negative');
        }

        $decoder = new RangeDecoder($stream);
        $p = LzmaModel::probabilities($properties);
        $output = '';
        $produced = 0;
        $state = 0;
        [$rep0, $rep1, $rep2, $rep3] = [0, 0, 0, 0];
        $posMask = (1 << $properties->pb) - 1;

        while ($produced < $length) {
            $posState = $produced & $posMask;

            if ($decoder->bit($p, LzmaModel::IS_MATCH + ($state << LzmaModel::POS_BITS_MAX) + $posState) === 0) {
                $previous = $produced > 0 ? ord($output[$produced - 1]) : 0;
                $offset = LzmaModel::literalOffset($properties, $produced, $previous);
                $symbol = 1;

                if ($state >= 7) {
                    $matchByte = ord($output[$produced - $rep0 - 1]);

                    do {
                        $matchBit = ($matchByte >> 7) & 1;
                        $matchByte <<= 1;
                        $bit = $decoder->bit($p, $offset + ((1 + $matchBit) << 8) + $symbol);
                        $symbol = ($symbol << 1) | $bit;
                    } while ($matchBit === $bit && $symbol < 0x100);
                }

                while ($symbol < 0x100) {
                    $symbol = ($symbol << 1) | $decoder->bit($p, $offset + $symbol);
                }

                $output .= chr($symbol - 0x100);
                $produced++;
                $state = LzmaModel::afterLiteral($state);

                continue;
            }

            if ($decoder->bit($p, LzmaModel::IS_REP + $state) === 1) {
                if ($produced === 0) {
                    throw CorruptLzmaStreamException::because('a repeated match precedes any output');
                }

                if ($decoder->bit($p, LzmaModel::IS_REP_G0 + $state) === 0) {
                    if ($decoder->bit($p, LzmaModel::IS_REP0_LONG + ($state << LzmaModel::POS_BITS_MAX) + $posState) === 0) {
                        $state = LzmaModel::afterShortRep($state);
                        $output .= $output[$produced - $rep0 - 1];
                        $produced++;

                        continue;
                    }
                } else {
                    if ($decoder->bit($p, LzmaModel::IS_REP_G1 + $state) === 0) {
                        $distance = $rep1;
                    } else {
                        if ($decoder->bit($p, LzmaModel::IS_REP_G2 + $state) === 0) {
                            $distance = $rep2;
                        } else {
                            $distance = $rep3;
                            $rep3 = $rep2;
                        }

                        $rep2 = $rep1;
                    }

                    $rep1 = $rep0;
                    $rep0 = $distance;
                }

                $len = self::length($decoder, $p, LzmaModel::REP_LEN, $posState);
                $state = LzmaModel::afterRep($state);
            } else {
                [$rep3, $rep2, $rep1] = [$rep2, $rep1, $rep0];
                $len = self::length($decoder, $p, LzmaModel::LEN, $posState);
                $state = LzmaModel::afterMatch($state);
                $rep0 = self::distance($decoder, $p, $len);

                if ($rep0 === LzmaModel::END_MARKER_DISTANCE) {
                    throw CorruptLzmaStreamException::because('the end marker arrives before the declared length');
                }

                if ($rep0 >= $produced || $rep0 >= $properties->dictionarySize) {
                    throw CorruptLzmaStreamException::because('a match distance points before the start of the data');
                }
            }

            $len += LzmaModel::MATCH_MIN_LEN;

            if ($produced + $len > $length) {
                throw CorruptLzmaStreamException::because('a match runs past the declared length');
            }

            // Byte by byte: an overlapping match (distance < length) repeats what it copies.
            for ($i = 0; $i < $len; $i++) {
                $output .= $output[$produced - $rep0 - 1];
                $produced++;
            }
        }

        return $output;
    }

    /**
     * @param  array<int, int>  $p
     */
    private static function length(RangeDecoder $decoder, array &$p, int $base, int $posState): int
    {
        if ($decoder->bit($p, $base + LzmaModel::LEN_CHOICE) === 0) {
            return $decoder->bitTree($p, $base + LzmaModel::LEN_LOW + ($posState << 3), 3);
        }

        if ($decoder->bit($p, $base + LzmaModel::LEN_CHOICE2) === 0) {
            return 8 + $decoder->bitTree($p, $base + LzmaModel::LEN_MID + ($posState << 3), 3);
        }

        return 16 + $decoder->bitTree($p, $base + LzmaModel::LEN_HIGH, 8);
    }

    /**
     * @param  array<int, int>  $p
     */
    private static function distance(RangeDecoder $decoder, array &$p, int $len): int
    {
        $lenState = min($len, LzmaModel::NUM_LEN_TO_POS_STATES - 1);
        $slot = $decoder->bitTree($p, LzmaModel::POS_SLOT + ($lenState << 6), 6);

        if ($slot < 4) {
            return $slot;
        }

        $directBits = ($slot >> 1) - 1;
        $distance = (2 | ($slot & 1)) << $directBits;

        if ($slot < LzmaModel::END_POS_MODEL_INDEX) {
            return $distance + $decoder->reverseBitTree($p, LzmaModel::POS_SPECIAL + $distance - $slot, $directBits);
        }

        $distance += $decoder->directBits($directBits - LzmaModel::ALIGN_BITS) << LzmaModel::ALIGN_BITS;

        return $distance + $decoder->reverseBitTree($p, LzmaModel::ALIGN, LzmaModel::ALIGN_BITS);
    }
}
