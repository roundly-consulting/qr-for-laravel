<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Encoder;

use RoundlyConsulting\Qr\Enums\Mode;
use RoundlyConsulting\Qr\Enums\Segmentation;

/**
 * Splits input into mode segments.
 *
 * Optimal segmentation minimises the bit-stream length (ISO/IEC 18004:2015 §7.4 and its
 * informative annex on optimisation of bit stream length) with a shortest-path dynamic
 * programme over characters × modes. Costs are kept in sixths of a bit so the fractional
 * per-character costs stay integral: byte = 48 per UTF-8 byte, alphanumeric = 33 (5.5 bits),
 * numeric = 20 (3⅓ bits), kanji = 78 (13 bits). Entering a mode costs its indicator plus
 * character-count field; switching first rounds the current mode's cost up to a whole bit.
 * The count field width only changes between version groups 1–9, 10–26 and 27–40, so a
 * segmentation is computed once per group.
 *
 * @internal
 */
final class Segmenter
{
    private const int BYTE_COST = 48;

    private const int ALPHANUMERIC_COST = 33;

    private const int NUMERIC_COST = 20;

    private const int KANJI_COST = 78;

    /**
     * @return list<Segment>
     */
    public static function segment(string $data, Segmentation $strategy, int $version, bool $kanji = false): array
    {
        if ($data === '') {
            return [];
        }

        return match ($strategy) {
            Segmentation::Byte => [Segment::bytes($data)],
            Segmentation::Single => [self::single($data, $kanji)],
            Segmentation::Optimal => self::optimal($data, $version, $kanji),
        };
    }

    /**
     * One segment in the tightest mode that covers the whole input.
     */
    public static function single(string $data, bool $kanji = false): Segment
    {
        if (Segment::isNumeric($data)) {
            return Segment::numeric($data);
        }

        if (Segment::isAlphanumeric($data)) {
            return Segment::alphanumeric($data);
        }

        if ($kanji && mb_check_encoding($data, 'UTF-8') && self::allKanji($data)) {
            return Segment::kanji($data);
        }

        return Segment::bytes($data);
    }

    /**
     * @return list<Segment>
     */
    public static function optimal(string $data, int $version, bool $kanji = false): array
    {
        if (! mb_check_encoding($data, 'UTF-8')) {
            return [Segment::bytes($data)];
        }

        $characters = mb_str_split($data, 1, 'UTF-8');
        $modes = self::modesFor($characters, $version, $kanji);
        $segments = [];
        $start = 0;
        $count = count($characters);

        for ($i = 1; $i <= $count; $i++) {
            if ($i === $count || $modes[$i] !== $modes[$start]) {
                $text = implode('', array_slice($characters, $start, $i - $start));

                $segments[] = match ($modes[$start]) {
                    Mode::Numeric => Segment::numeric($text),
                    Mode::Alphanumeric => Segment::alphanumeric($text),
                    Mode::Kanji => Segment::kanji($text),
                    default => Segment::bytes($text),
                };

                $start = $i;
            }
        }

        return $segments;
    }

    /**
     * The cheapest mode for every character.
     *
     * @param  list<string>  $characters
     * @return list<Mode>
     */
    private static function modesFor(array $characters, int $version, bool $kanji): array
    {
        $available = self::availableModes($kanji);
        $head = array_map(static fn (Mode $mode): int => (4 + $mode->charCountBits($version)) * 6, $available);
        $previous = $head;
        $trail = [];

        foreach ($characters as $i => $character) {
            $costs = [];
            $trail[$i] = [];

            // Stay in each mode that can encode the character.
            foreach ($available as $index => $mode) {
                $cost = self::characterCost($mode, $character);

                if ($cost !== null) {
                    $costs[$index] = $previous[$index] + $cost;
                    $trail[$i][$index] = $index;
                }
            }

            // Or finish the character in one mode and switch to another afterwards.
            foreach ($head as $to => $headCost) {
                foreach ($costs as $from => $fromCost) {
                    $switched = intdiv($fromCost + 5, 6) * 6 + $headCost;

                    if (! isset($costs[$to]) || $switched < $costs[$to]) {
                        $costs[$to] = $switched;
                        $trail[$i][$to] = $trail[$i][$from];
                    }
                }
            }

            $previous = $costs;
        }

        $current = self::cheapest($previous);
        $result = [];

        for ($i = count($characters) - 1; $i >= 0; $i--) {
            $current = $trail[$i][$current];
            $result[$i] = $available[$current];
        }

        ksort($result);

        return array_values($result);
    }

    /**
     * @return list<Mode>
     */
    private static function availableModes(bool $kanji): array
    {
        return $kanji
            ? [Mode::Byte, Mode::Alphanumeric, Mode::Numeric, Mode::Kanji]
            : [Mode::Byte, Mode::Alphanumeric, Mode::Numeric];
    }

    /**
     * The index of the lowest cost (first one on ties).
     *
     * @param  array<int, int>  $costs
     */
    private static function cheapest(array $costs): int
    {
        $best = 0;
        $bestCost = PHP_INT_MAX;

        foreach ($costs as $index => $cost) {
            if ($cost < $bestCost) {
                $best = $index;
                $bestCost = $cost;
            }
        }

        return $best;
    }

    private static function characterCost(Mode $mode, string $character): ?int
    {
        return match ($mode) {
            Mode::Byte => self::BYTE_COST * strlen($character),
            Mode::Alphanumeric => Segment::isAlphanumeric($character) ? self::ALPHANUMERIC_COST : null,
            Mode::Numeric => Segment::isNumeric($character) ? self::NUMERIC_COST : null,
            Mode::Kanji => ShiftJis::isKanji($character) ? self::KANJI_COST : null,
            default => null,
        };
    }

    private static function allKanji(string $data): bool
    {
        foreach (mb_str_split($data, 1, 'UTF-8') as $character) {
            if (! ShiftJis::isKanji($character)) {
                return false;
            }
        }

        return true;
    }
}
