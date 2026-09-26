<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Compression\Lzma;

/**
 * Raw LZMA1 encoder — the mirror of {@see LzmaDecoder}: the same probability model and
 * state machine, with every decoded bit encoded instead. It parses greedily with a one-step
 * lazy check (a literal is emitted when the next position holds a longer match), prefers a
 * repeated distance when it is nearly as long as a new match, and always terminates the
 * stream with the end-of-payload marker (a match of length 2 at distance 0xFFFFFFFF).
 *
 * Output is deterministic but is not byte-identical to other LZMA encoders; any conforming
 * decoder reproduces the input.
 *
 * @internal
 */
final class LzmaEncoder
{
    /** @var array<int, int> */
    private array $p;

    private RangeEncoder $encoder;

    private int $state = 0;

    /** @var array<int, int> rep0..rep3 (0-based distances) */
    private array $reps = [0, 0, 0, 0];

    private function __construct(private readonly string $data, private readonly LzmaProperties $properties)
    {
        $this->p = LzmaModel::probabilities($properties);
        $this->encoder = new RangeEncoder;
    }

    public static function encode(string $data, LzmaProperties $properties = new LzmaProperties): string
    {
        return (new self($data, $properties))->run();
    }

    private function run(): string
    {
        $finder = new MatchFinder($this->data, $this->properties->dictionarySize);
        $length = strlen($this->data);
        $position = 0;

        while ($position < $length) {
            $position += $this->step($finder, $position);
        }

        $this->encodeMatch($position, LzmaModel::END_MARKER_DISTANCE, LzmaModel::MATCH_MIN_LEN);

        return $this->encoder->finish();
    }

    /**
     * Encode one symbol at $position and return how many bytes it covered.
     */
    private function step(MatchFinder $finder, int $position): int
    {
        if ($position === 0) {
            $this->encodeLiteral($position);

            return 1;
        }

        [$repLength, $repIndex] = $this->longestRep($finder, $position);
        [$matchLength, $matchDistance] = $finder->longest($position);

        if ($repLength >= LzmaModel::MATCH_MIN_LEN && $repLength + 1 >= $matchLength) {
            $this->encodeRep($position, $repIndex, $repLength);

            return $repLength;
        }

        $worthMatching = $matchLength >= 3 || ($matchLength === 2 && $matchDistance < 128);

        if ($worthMatching && $position + 1 < strlen($this->data)) {
            [$nextLength] = $finder->longest($position + 1);

            if ($nextLength >= $matchLength + 1) {
                $worthMatching = false;
            }
        }

        if ($worthMatching) {
            $this->encodeMatch($position, $matchDistance, $matchLength);

            return $matchLength;
        }

        if ($this->data[$position] === $this->data[$position - $this->reps[0] - 1]) {
            $this->encodeShortRep($position);

            return 1;
        }

        $this->encodeLiteral($position);

        return 1;
    }

    /**
     * @return array{0: int, 1: int} length, rep index
     */
    private function longestRep(MatchFinder $finder, int $position): array
    {
        $best = [0, 0];

        foreach ($this->reps as $index => $distance) {
            if ($distance < $position) {
                $length = $finder->lengthAt($position, $distance);

                if ($length > $best[0]) {
                    $best = [$length, $index];
                }
            }
        }

        return $best;
    }

    private function encodeLiteral(int $position): void
    {
        $posState = $position & ((1 << $this->properties->pb) - 1);
        $this->encoder->bit($this->p, LzmaModel::IS_MATCH + ($this->state << LzmaModel::POS_BITS_MAX) + $posState, 0);

        $previous = $position > 0 ? ord($this->data[$position - 1]) : 0;
        $offset = LzmaModel::literalOffset($this->properties, $position, $previous);
        $byte = ord($this->data[$position]);
        $symbol = 1;

        if ($this->state >= 7) {
            $matchByte = ord($this->data[$position - $this->reps[0] - 1]);
            $matched = true;

            for ($i = 7; $i >= 0; $i--) {
                $bit = ($byte >> $i) & 1;

                if ($matched) {
                    $matchBit = ($matchByte >> $i) & 1;
                    $this->encoder->bit($this->p, $offset + ((1 + $matchBit) << 8) + $symbol, $bit);
                    $matched = $matchBit === $bit;
                } else {
                    $this->encoder->bit($this->p, $offset + $symbol, $bit);
                }

                $symbol = ($symbol << 1) | $bit;
            }
        } else {
            for ($i = 7; $i >= 0; $i--) {
                $bit = ($byte >> $i) & 1;
                $this->encoder->bit($this->p, $offset + $symbol, $bit);
                $symbol = ($symbol << 1) | $bit;
            }
        }

        $this->state = LzmaModel::afterLiteral($this->state);
    }

    private function encodeMatch(int $position, int $distance, int $length): void
    {
        $posState = $position & ((1 << $this->properties->pb) - 1);
        $this->encoder->bit($this->p, LzmaModel::IS_MATCH + ($this->state << LzmaModel::POS_BITS_MAX) + $posState, 1);
        $this->encoder->bit($this->p, LzmaModel::IS_REP + $this->state, 0);
        $this->encodeLength(LzmaModel::LEN, $length - LzmaModel::MATCH_MIN_LEN, $posState);
        $this->encodeDistance($distance, $length - LzmaModel::MATCH_MIN_LEN);

        $this->reps = [$distance, $this->reps[0], $this->reps[1], $this->reps[2]];
        $this->state = LzmaModel::afterMatch($this->state);
    }

    private function encodeRep(int $position, int $index, int $length): void
    {
        $posState = $position & ((1 << $this->properties->pb) - 1);
        $this->encoder->bit($this->p, LzmaModel::IS_MATCH + ($this->state << LzmaModel::POS_BITS_MAX) + $posState, 1);
        $this->encoder->bit($this->p, LzmaModel::IS_REP + $this->state, 1);

        if ($index === 0) {
            $this->encoder->bit($this->p, LzmaModel::IS_REP_G0 + $this->state, 0);
            $this->encoder->bit($this->p, LzmaModel::IS_REP0_LONG + ($this->state << LzmaModel::POS_BITS_MAX) + $posState, 1);
        } else {
            $this->encoder->bit($this->p, LzmaModel::IS_REP_G0 + $this->state, 1);
            $this->encoder->bit($this->p, LzmaModel::IS_REP_G1 + $this->state, $index === 1 ? 0 : 1);

            if ($index > 1) {
                $this->encoder->bit($this->p, LzmaModel::IS_REP_G2 + $this->state, $index === 2 ? 0 : 1);
            }

            $distance = $this->reps[$index];

            // Move the used distance to the front, shifting the ones before it back.
            for ($i = $index; $i > 0; $i--) {
                $this->reps[$i] = $this->reps[$i - 1];
            }

            $this->reps[0] = $distance;
        }

        $this->encodeLength(LzmaModel::REP_LEN, $length - LzmaModel::MATCH_MIN_LEN, $posState);
        $this->state = LzmaModel::afterRep($this->state);
    }

    private function encodeShortRep(int $position): void
    {
        $posState = $position & ((1 << $this->properties->pb) - 1);
        $this->encoder->bit($this->p, LzmaModel::IS_MATCH + ($this->state << LzmaModel::POS_BITS_MAX) + $posState, 1);
        $this->encoder->bit($this->p, LzmaModel::IS_REP + $this->state, 1);
        $this->encoder->bit($this->p, LzmaModel::IS_REP_G0 + $this->state, 0);
        $this->encoder->bit($this->p, LzmaModel::IS_REP0_LONG + ($this->state << LzmaModel::POS_BITS_MAX) + $posState, 0);
        $this->state = LzmaModel::afterShortRep($this->state);
    }

    private function encodeLength(int $base, int $length, int $posState): void
    {
        if ($length < 8) {
            $this->encoder->bit($this->p, $base + LzmaModel::LEN_CHOICE, 0);
            $this->encoder->bitTree($this->p, $base + LzmaModel::LEN_LOW + ($posState << 3), 3, $length);

            return;
        }

        $this->encoder->bit($this->p, $base + LzmaModel::LEN_CHOICE, 1);

        if ($length < 16) {
            $this->encoder->bit($this->p, $base + LzmaModel::LEN_CHOICE2, 0);
            $this->encoder->bitTree($this->p, $base + LzmaModel::LEN_MID + ($posState << 3), 3, $length - 8);

            return;
        }

        $this->encoder->bit($this->p, $base + LzmaModel::LEN_CHOICE2, 1);
        $this->encoder->bitTree($this->p, $base + LzmaModel::LEN_HIGH, 8, $length - 16);
    }

    private function encodeDistance(int $distance, int $length): void
    {
        $lenState = min($length, LzmaModel::NUM_LEN_TO_POS_STATES - 1);
        $slot = LzmaModel::positionSlot($distance);
        $this->encoder->bitTree($this->p, LzmaModel::POS_SLOT + ($lenState << 6), 6, $slot);

        if ($slot < 4) {
            return;
        }

        $footerBits = ($slot >> 1) - 1;
        $base = (2 | ($slot & 1)) << $footerBits;
        $reduced = $distance - $base;

        if ($slot < LzmaModel::END_POS_MODEL_INDEX) {
            $this->encoder->reverseBitTree($this->p, LzmaModel::POS_SPECIAL + $base - $slot, $footerBits, $reduced);

            return;
        }

        $this->encoder->directBits($reduced >> LzmaModel::ALIGN_BITS, $footerBits - LzmaModel::ALIGN_BITS);
        $this->encoder->reverseBitTree($this->p, LzmaModel::ALIGN, LzmaModel::ALIGN_BITS, $reduced & ((1 << LzmaModel::ALIGN_BITS) - 1));
    }
}
