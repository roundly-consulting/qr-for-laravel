<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Enums\BySquare;

use RoundlyConsulting\Enums\Helpers;

enum Periodicity: string
{
    use Helpers;

    case Daily = 'd';
    case Weekly = 'w';
    case Biweekly = 'b';
    case Monthly = 'm';
    case Bimonthly = 'B';
    case Quarterly = 'q';
    case Semiannually = 's';
    case Annually = 'a';

    /**
     * Weekly periods address the execution day as a weekday (1–7), not a day of the month.
     */
    public function usesWeekday(): bool
    {
        return $this === self::Weekly || $this === self::Biweekly;
    }
}
