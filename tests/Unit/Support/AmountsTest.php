<?php

declare(strict_types=1);

use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Qr\Support\Amounts;

it('renders exact trimmed decimals', function (Money $money, string $decimal): void {
    expect(Amounts::decimal($money))->toBe($decimal);
})->with([
    [fn () => Money::ofMinor(1230, 'EUR'), '12.3'],
    [fn () => Money::ofMinor(1000, 'EUR'), '10'],
    [fn () => Money::ofMinor(0, 'EUR'), '0'],
    [fn () => Money::ofMinor(1, 'EUR'), '0.01'],
    [fn () => Money::ofMinor('00012300', 'EUR'), '123'],
    [fn () => Money::ofMinor(5, 'JPY'), '5'],
    [fn () => Money::ofMinor(1, 'BHD'), '0.001'],
    [fn () => Money::ofMinor('99999999999', 'EUR'), '999999999.99'],
]);

it('exposes canonical strings and currency facts', function (): void {
    $money = Money::ofMinor('00012300', 'EUR');

    expect(Amounts::minor($money))->toBe('12300')
        ->and(Amounts::currencyCode($money))->toBe('EUR')
        ->and(Amounts::exponent(Money::ofMinor(1, 'BHD')))->toBe(3)
        ->and(Amounts::isPositive($money))->toBeTrue()
        ->and(Amounts::isNegative(Money::ofMinor(-1, 'EUR')))->toBeTrue()
        ->and(Amounts::isIsoCurrency($money))->toBeTrue()
        ->and(Amounts::isIsoCurrency(Money::ofMinor(1, Currency::custom('PTS', 18))))->toBeFalse()
        ->and(Amounts::isIsoCode('eur'))->toBeTrue()
        ->and(Amounts::isIsoCode('XYZ'))->toBeFalse()
        ->and(Amounts::sameCurrency($money, 'eur'))->toBeTrue()
        ->and(Amounts::fromDecimal('12.3', 'EUR')->minor())->toBe('1230');
});

it('checks the EPC amount range on the canonical minor string', function (int|string $minor, bool $fits): void {
    expect(Amounts::fitsEpcRange(Money::ofMinor($minor, 'EUR')))->toBe($fits);
})->with([
    [1, true],
    ['99999999999', true],
    ['100000000000', false],
    [0, false],
    [-1, false],
]);

it('checks the PAY by square amount length on the decimal string', function (Money $money, bool $fits): void {
    expect(Amounts::fitsBySquareAmount($money))->toBe($fits);
})->with([
    [fn () => Money::ofMinor(0, 'EUR'), true],
    [fn () => Money::ofMinor('999999999999999', 'JPY'), true],
    [fn () => Money::ofMinor('9999999999999999', 'JPY'), false],
    [fn () => Money::ofMinor('1234567890123', 'EUR'), true],
    [fn () => Money::ofMinor('12345678901234', 'EUR'), true],
    [fn () => Money::ofMinor('123456789012345', 'EUR'), false],
    [fn () => Money::ofMinor(-100, 'EUR'), false],
]);
