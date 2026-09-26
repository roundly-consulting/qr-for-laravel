<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Enums;

use RoundlyConsulting\Enums\Helpers;

enum ModuleStyle: string
{
    use Helpers;

    case Square = 'square';
    case Rounded = 'rounded';
    case Dots = 'dots';
}
