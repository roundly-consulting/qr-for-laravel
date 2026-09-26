<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Enums;

use RoundlyConsulting\Enums\Helpers;

enum DataUriEncoding: string
{
    use Helpers;

    case Percent = 'percent';
    case Base64 = 'base64';
}
