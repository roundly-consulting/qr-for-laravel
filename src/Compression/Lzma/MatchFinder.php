<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Compression\Lzma;

/**
 * Hash-chain match finder over the whole input: a 3-byte hash heads a chain of earlier
 * positions, walked to a fixed depth. Deterministic, so the encoder's output is stable.
 *
 * @internal
 */
final class MatchFinder
{
    public const int CHAIN_DEPTH = 32;

    /** @var array<int, int> hash => most recent position */
    private array $head = [];

    /** @var array<int, int> position => previous position with the same hash */
    private array $previous = [];

    private int $inserted = 0;

    private readonly int $length;

    public function __construct(private readonly string $data, private readonly int $maxDistance)
    {
        $this->length = strlen($data);
    }

    /**
     * The longest match at $position (length, 0-based distance), preferring the nearest on
     * ties; length 0 when there is none.
     *
     * @return array{0: int, 1: int}
     */
    public function longest(int $position): array
    {
        $this->insertUpTo($position);

        $limit = min(LzmaModel::MATCH_MAX_LEN, $this->length - $position);

        if ($limit < LzmaModel::MATCH_MIN_LEN) {
            return [0, 0];
        }

        $bestLength = 0;
        $bestDistance = 0;

        // Two-byte matches only pay off when close; look back a short window for them.
        for ($candidate = $position - 1, $floor = max(0, $position - 128); $candidate >= $floor; $candidate--) {
            if ($this->data[$candidate] === $this->data[$position] && $this->data[$candidate + 1] === $this->data[$position + 1]) {
                $bestLength = 2;
                $bestDistance = $position - $candidate - 1;

                break;
            }
        }

        if ($limit >= 3) {
            $candidate = $this->head[$this->hash($position)] ?? -1;

            for ($depth = 0; $candidate >= 0 && $depth < self::CHAIN_DEPTH; $depth++) {
                $distance = $position - $candidate - 1;

                if ($distance >= $this->maxDistance) {
                    break;
                }

                $length = $this->matchLength($candidate, $position, $limit);

                if ($length > $bestLength) {
                    $bestLength = $length;
                    $bestDistance = $distance;

                    if ($length === $limit) {
                        break;
                    }
                }

                $candidate = $this->previous[$candidate] ?? -1;
            }
        }

        return [$bestLength, $bestDistance];
    }

    /**
     * How many bytes at $position repeat the bytes $distance + 1 back (0-based distance).
     */
    public function lengthAt(int $position, int $distance): int
    {
        $source = $position - $distance - 1;

        if ($source < 0) {
            return 0;
        }

        return $this->matchLength($source, $position, min(LzmaModel::MATCH_MAX_LEN, $this->length - $position));
    }

    private function matchLength(int $source, int $position, int $limit): int
    {
        $length = 0;

        while ($length < $limit && $this->data[$source + $length] === $this->data[$position + $length]) {
            $length++;
        }

        return $length;
    }

    /**
     * Index every position before $position (positions with three bytes available).
     */
    private function insertUpTo(int $position): void
    {
        for (; $this->inserted < $position; $this->inserted++) {
            if ($this->inserted + 3 > $this->length) {
                continue;
            }

            $hash = $this->hash($this->inserted);
            $this->previous[$this->inserted] = $this->head[$hash] ?? -1;
            $this->head[$hash] = $this->inserted;
        }
    }

    private function hash(int $position): int
    {
        return (ord($this->data[$position]) << 16) | (ord($this->data[$position + 1]) << 8) | ord($this->data[$position + 2]);
    }
}
