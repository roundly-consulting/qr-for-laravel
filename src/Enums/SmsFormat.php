<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Enums;

use RoundlyConsulting\Enums\Helpers;

enum SmsFormat: string
{
    use Helpers;

    /** `SMSTO:+421900000000:message` — the form most camera apps understand. */
    case Smsto = 'smsto';

    /** `sms:+421900000000?body=message` (RFC 5724). */
    case Uri = 'sms';
}
