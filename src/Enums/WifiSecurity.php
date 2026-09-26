<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Enums;

use RoundlyConsulting\Enums\Helpers;

/**
 * The `T:` field of a `WIFI:` network configuration code.
 */
enum WifiSecurity: string
{
    use Helpers;

    case Wpa = 'WPA';
    case Wep = 'WEP';

    /** WPA3 personal (simultaneous authentication of equals). */
    case Sae = 'SAE';

    case None = 'nopass';
}
