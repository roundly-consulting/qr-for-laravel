<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Enums\BySquare;

use RoundlyConsulting\Enums\Helpers;

/**
 * The by square specification version, carried in the low nibble of the first header byte.
 */
enum BySquareVersion: int
{
    use Helpers;

    /** 1.0.0 (2013-02-22). */
    case V1_0_0 = 0;

    /** 1.1.0 (2015-06-24) — adds the beneficiary name/street/city block. */
    case V1_1_0 = 1;

    /** 1.2.0 (2025-04-01) — the beneficiary name becomes mandatory. */
    case V1_2_0 = 2;

    public function semver(): string
    {
        return match ($this) {
            self::V1_0_0 => '1.0.0',
            self::V1_1_0 => '1.1.0',
            self::V1_2_0 => '1.2.0',
        };
    }

    public function requiresBeneficiaryName(): bool
    {
        return $this === self::V1_2_0;
    }

    public function hasBeneficiaryBlock(): bool
    {
        return $this !== self::V1_0_0;
    }

    public static function tryFromSemver(string $semver): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->semver() === trim($semver)) {
                return $case;
            }
        }

        return null;
    }
}
