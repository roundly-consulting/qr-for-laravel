<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Enums;

use RoundlyConsulting\Enums\Helpers;

/**
 * How input text is split into mode segments.
 */
enum Segmentation: string
{
    use Helpers;

    /** Shortest bit stream over mixed numeric/alphanumeric/byte(/kanji) segments. */
    case Optimal = 'optimal';

    /** One segment in the tightest mode covering the whole input. */
    case Single = 'single';

    /** One byte-mode segment of the raw bytes. */
    case Byte = 'byte';
}
