<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Enums;

use RoundlyConsulting\Enums\Helpers;

/**
 * When to prefix the symbol with an ECI 26 (UTF-8) designator.
 */
enum EciMode: string
{
    use Helpers;

    /**
     * Only when the input is valid UTF-8 and a byte segment carries a non-ASCII byte. Kanji
     * segments are never combined with a designator (see {@see self::Always}).
     */
    case Auto = 'auto';

    /** Always; kanji characters are then encoded as UTF-8 bytes instead of kanji segments. */
    case Always = 'always';

    case Never = 'never';
}
