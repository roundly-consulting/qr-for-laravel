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

    /** Only when the input is valid UTF-8 and contains a non-ASCII byte. */
    case Auto = 'auto';

    case Always = 'always';

    case Never = 'never';
}
