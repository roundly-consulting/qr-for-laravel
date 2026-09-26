<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Enums\BySquare;

use RoundlyConsulting\Enums\Helpers;

enum PaymentType: int
{
    use Helpers;

    case PaymentOrder = 1;
    case StandingOrder = 2;
    case DirectDebit = 4;
}
