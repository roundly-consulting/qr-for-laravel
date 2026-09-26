<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Enums;

use RoundlyConsulting\Enums\Helpers;

/**
 * The EPC069-12 v3.1 §2.2 "Version" element: 001 requires a BIC, 002 makes it
 * optional for EEA IBANs.
 */
enum EpcVersion: string
{
    use Helpers;

    case V001 = '001';
    case V002 = '002';
}
