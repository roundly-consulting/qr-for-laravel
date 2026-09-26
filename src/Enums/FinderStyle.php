<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Enums;

use RoundlyConsulting\Enums\Helpers;

enum FinderStyle: string
{
    use Helpers;

    case Square = 'square';
    case Rounded = 'rounded';
}
