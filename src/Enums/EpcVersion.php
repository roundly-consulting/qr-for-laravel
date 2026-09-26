<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Enums;

use RoundlyConsulting\Enums\Helpers;
use RoundlyConsulting\Qr\Banking\Iban;

/**
 * The EPC069-12 v3.1 §2.2 "Version" element: 001 requires a BIC, 002 makes it
 * optional for EEA IBANs.
 */
enum EpcVersion: string
{
    use Helpers;

    case V001 = '001';
    case V002 = '002';

    /**
     * Whether a payment to this IBAN must carry a BIC: always in 001, outside the EEA in 002.
     */
    public function bicRequiredFor(Iban $iban): bool
    {
        return $this === self::V001 || ! $iban->isEea();
    }
}
