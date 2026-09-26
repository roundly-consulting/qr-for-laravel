<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Exceptions;

use RoundlyConsulting\Qr\Enums\Mode;

/**
 * A segment forced into numeric, alphanumeric or kanji mode contains a character that mode
 * cannot carry. Reports the position only.
 */
final class UnencodableCharacterException extends QrException
{
    public const string REASON_UNENCODABLE_CHARACTER = 'unencodable_character';

    public static function at(Mode $mode, int $position): self
    {
        return new self(
            sprintf('The character at position %d cannot be encoded in %s mode.', $position, $mode->key()),
            self::REASON_UNENCODABLE_CHARACTER,
            'data',
        );
    }
}
