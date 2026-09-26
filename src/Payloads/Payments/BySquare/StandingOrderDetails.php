<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads\Payments\BySquare;

use Carbon\CarbonInterface;
use RoundlyConsulting\Qr\Enums\BySquare\Month;
use RoundlyConsulting\Qr\Enums\BySquare\Periodicity;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;

/**
 * The standing-order extension: how often, on which day (1–7 = weekday for weekly
 * periods, 1–31 otherwise; none for daily), in which months and until when.
 */
final readonly class StandingOrderDetails
{
    /**
     * @param  list<Month>  $months
     *
     * @throws InvalidPayloadException
     */
    public function __construct(
        public Periodicity $periodicity,
        public ?int $day = null,
        public array $months = [],
        public ?CarbonInterface $lastDate = null,
    ) {
        if ($day === null) {
            return;
        }

        $max = $periodicity->usesWeekday() ? 7 : 31;

        if ($periodicity === Periodicity::Daily || $day < 1 || $day > $max) {
            throw InvalidPayloadException::outOfRange(PayBySquare::TYPE, 'standingOrder.day');
        }
    }
}
