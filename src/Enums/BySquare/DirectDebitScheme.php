<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Enums\BySquare;

use RoundlyConsulting\Enums\Helpers;

enum DirectDebitScheme: int
{
    use Helpers;

    case Other = 0;
    case Sepa = 1;
}
