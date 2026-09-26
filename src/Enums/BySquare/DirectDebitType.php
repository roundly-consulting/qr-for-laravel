<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Enums\BySquare;

use RoundlyConsulting\Enums\Helpers;

enum DirectDebitType: int
{
    use Helpers;

    case OneOff = 0;
    case Recurrent = 1;
}
