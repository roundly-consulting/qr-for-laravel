<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Encoder;

use RoundlyConsulting\Qr\Enums\ErrorCorrection;

/**
 * Splits data codewords into error-correction blocks, appends each block's Reed-Solomon
 * codewords and interleaves the result (ISO/IEC 18004:2015 §7.6). Short blocks come first;
 * long blocks carry one extra data codeword.
 *
 * @internal
 */
final class Interleaver
{
    /**
     * @param  list<int>  $data  exactly Capacity::dataCodewords() codewords
     * @return list<int> the final codeword sequence
     */
    public static function interleave(array $data, int $version, ErrorCorrection $ecc): array
    {
        $blocks = self::split($data, $version, $ecc);
        $result = [];
        $longest = count($blocks[count($blocks) - 1]->data);

        for ($i = 0; $i < $longest; $i++) {
            foreach ($blocks as $block) {
                if ($i < count($block->data)) {
                    $result[] = $block->data[$i];
                }
            }
        }

        for ($i = 0, $eccLength = Capacity::eccCodewordsPerBlock($version, $ecc); $i < $eccLength; $i++) {
            foreach ($blocks as $block) {
                $result[] = $block->ecc[$i];
            }
        }

        return $result;
    }

    /**
     * Reverse of {@see self::interleave()}: the blocks in block order.
     *
     * @param  list<int>  $codewords
     * @return list<CodewordBlock>
     */
    public static function deinterleave(array $codewords, int $version, ErrorCorrection $ecc): array
    {
        $numBlocks = Capacity::numBlocks($version, $ecc);
        $eccLength = Capacity::eccCodewordsPerBlock($version, $ecc);
        $numShort = self::shortBlocks($version, $ecc);
        $shortData = self::shortBlockDataLength($version, $ecc);
        $data = array_fill(0, $numBlocks, []);
        $eccWords = array_fill(0, $numBlocks, []);
        $position = 0;

        for ($i = 0; $i <= $shortData; $i++) {
            for ($b = 0; $b < $numBlocks; $b++) {
                if ($i < $shortData || $b >= $numShort) {
                    $data[$b][] = $codewords[$position++];
                }
            }
        }

        for ($i = 0; $i < $eccLength; $i++) {
            for ($b = 0; $b < $numBlocks; $b++) {
                $eccWords[$b][] = $codewords[$position++];
            }
        }

        $blocks = [];

        for ($b = 0; $b < $numBlocks; $b++) {
            $blocks[] = new CodewordBlock($data[$b], $eccWords[$b]);
        }

        return $blocks;
    }

    /**
     * @param  list<int>  $data
     * @return list<CodewordBlock>
     */
    public static function split(array $data, int $version, ErrorCorrection $ecc): array
    {
        $numBlocks = Capacity::numBlocks($version, $ecc);
        $eccLength = Capacity::eccCodewordsPerBlock($version, $ecc);
        $numShort = self::shortBlocks($version, $ecc);
        $shortData = self::shortBlockDataLength($version, $ecc);
        $blocks = [];
        $offset = 0;

        for ($b = 0; $b < $numBlocks; $b++) {
            $length = $shortData + ($b < $numShort ? 0 : 1);
            $blockData = array_slice($data, $offset, $length);
            $offset += $length;
            $blocks[] = new CodewordBlock($blockData, ReedSolomon::remainder($blockData, $eccLength));
        }

        return $blocks;
    }

    private static function shortBlocks(int $version, ErrorCorrection $ecc): int
    {
        $numBlocks = Capacity::numBlocks($version, $ecc);

        return $numBlocks - Capacity::totalCodewords($version) % $numBlocks;
    }

    private static function shortBlockDataLength(int $version, ErrorCorrection $ecc): int
    {
        return intdiv(Capacity::totalCodewords($version), Capacity::numBlocks($version, $ecc))
            - Capacity::eccCodewordsPerBlock($version, $ecc);
    }
}
