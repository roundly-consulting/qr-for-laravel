<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Support;

use Closure;
use RoundlyConsulting\Qr\ValueObjects\QrMatrix;

/**
 * A bounded in-process LRU of encoded matrices for public payloads. The caller decides
 * what may enter it: secret and personal payloads never do, so a long-running worker
 * never holds their data.
 *
 * @internal
 */
final class MatrixMemo
{
    /** @var array<string, QrMatrix> */
    private array $entries = [];

    public function __construct(private readonly int $capacity) {}

    /**
     * @param  Closure(): QrMatrix  $encode
     */
    public function remember(string $key, Closure $encode): QrMatrix
    {
        if ($this->capacity === 0) {
            return $encode();
        }

        if (isset($this->entries[$key])) {
            // Move to the most-recently-used end.
            $matrix = $this->entries[$key];
            unset($this->entries[$key]);

            return $this->entries[$key] = $matrix;
        }

        $this->entries[$key] = $encode();

        if (count($this->entries) > $this->capacity) {
            unset($this->entries[array_key_first($this->entries)]);
        }

        return $this->entries[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->entries[$key]);
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function flush(): void
    {
        $this->entries = [];
    }
}
