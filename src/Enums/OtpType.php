<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Enums;

use RoundlyConsulting\Enums\Helpers;

enum OtpType: string
{
    use Helpers;

    case Totp = 'totp';
    case Hotp = 'hotp';
}
