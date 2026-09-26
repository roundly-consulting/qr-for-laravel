<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Encoder;

/**
 * Arithmetic in GF(2^8) with the QR primitive polynomial x^8 + x^4 + x^3 + x^2 + 1 (0x11D)
 * and generator α = 2 (ISO/IEC 18004:2015 §7.5.2). Tables are input-independent and built
 * once per process.
 *
 * @internal
 */
final class GaloisField
{
    public const int PRIMITIVE = 0x11D;

    /** @var array<int, int> α^i for i in 0..511 (doubled so a log sum never needs a modulo) */
    private static array $exp = [];

    /** @var array<int, int> log_α(x) for x in 1..255 */
    private static array $log = [];

    public static function exp(int $power): int
    {
        self::boot();

        return self::$exp[$power % 255];
    }

    public static function log(int $value): int
    {
        self::boot();

        return self::$log[$value];
    }

    public static function multiply(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }

        self::boot();

        return self::$exp[self::$log[$a] + self::$log[$b]];
    }

    private static function boot(): void
    {
        if (self::$exp !== []) {
            return;
        }

        $value = 1;

        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $value;
            self::$log[$value] = $i;
            $value <<= 1;

            if ($value & 0x100) {
                $value ^= self::PRIMITIVE;
            }
        }

        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }
}
