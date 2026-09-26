<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Exceptions;

/**
 * The compressed LZMA stream inside a PAY by square code is invalid.
 */
final class CorruptLzmaStreamException extends BySquareDecodeException
{
    public static function because(string $problem): self
    {
        return new self(sprintf('Corrupt LZMA stream: %s.', $problem), self::REASON_LZMA);
    }
}
