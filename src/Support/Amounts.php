<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Support;

use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Money;
use Throwable;

/**
 * The single seam to money-for-laravel: the only class that calls Money/Currency methods
 * (payload classes merely type-hint Money). Amounts stay money's canonical integer strings
 * — never floats, never int casts.
 *
 * @internal
 */
final class Amounts
{
    /** EPC069-12 AT-T002 maximum: 999 999 999.99 euro = 11 minor digits. */
    private const int EPC_MAX_MINOR_DIGITS = 11;

    /** by square: the decimal amount string is at most 15 characters. */
    private const int BY_SQUARE_MAX_LENGTH = 15;

    /**
     * Exact decimal with trailing zeros trimmed: 1230 EUR → "12.3", 1000 → "10", 0 → "0".
     */
    public static function decimal(Money $money): string
    {
        $decimal = $money->toDecimal(trimTrailingZeros: true);

        if (str_contains($decimal, '.')) {
            $decimal = rtrim(rtrim($decimal, '0'), '.');
        }

        return $decimal === '' || $decimal === '-0' ? '0' : $decimal;
    }

    public static function minor(Money $money): string
    {
        return $money->minor();
    }

    public static function currencyCode(Money $money): string
    {
        return $money->currency()->code;
    }

    public static function exponent(Money $money): int
    {
        return $money->currency()->exponent;
    }

    public static function isPositive(Money $money): bool
    {
        return $money->isPositive();
    }

    public static function isNegative(Money $money): bool
    {
        return $money->isNegative();
    }

    public static function isIsoCurrency(Money $money): bool
    {
        return $money->currency()->iso;
    }

    /**
     * Whether a bare currency code (an amount-less payment) is a registered ISO 4217 currency.
     */
    public static function isIsoCode(string $code): bool
    {
        try {
            return Currency::of($code)->iso;
        } catch (Throwable) {
            return false;
        }
    }

    public static function sameCurrency(Money $money, string $code): bool
    {
        return $money->currency()->code === strtoupper($code);
    }

    /**
     * EPC AT-T002: 0.01 ≤ amount ≤ 999 999 999.99 — a positive canonical minor string of at
     * most 11 digits (no sign, no leading zeros).
     */
    public static function fitsEpcRange(Money $money): bool
    {
        return $money->isPositive() && strlen($money->minor()) <= self::EPC_MAX_MINOR_DIGITS;
    }

    /**
     * by square: amount ≥ 0 with a decimal representation of at most 15 characters.
     */
    public static function fitsBySquareAmount(Money $money): bool
    {
        return ! $money->isNegative() && strlen(self::decimal($money)) <= self::BY_SQUARE_MAX_LENGTH;
    }

    /**
     * Parse a decimal major amount ("12.3") in a currency — used by the payment parsers.
     */
    public static function fromDecimal(string $decimal, string $currency): Money
    {
        return Money::ofMajor($decimal, $currency);
    }
}
