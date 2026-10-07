<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads\Payments\BySquare;

use Carbon\CarbonInterface;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Qr\Enums\BySquare\PaymentType;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;
use RoundlyConsulting\Qr\Exceptions\UnsupportedCurrencyException;
use RoundlyConsulting\Qr\Support\Amounts;
use RoundlyConsulting\Qr\Support\TextNormalizer;

/**
 * One payment of a PAY by square document: a payment order, a standing order or a direct
 * debit. Amounts are optional (e.g. a voluntary donation) — then the ISO currency is
 * given on its own.
 */
final readonly class Payment
{
    public string $currency;

    public ?string $originatorsReference;

    public ?string $note;

    /**
     * @param  list<BankAccount>  $accounts  at least one
     *
     * @throws InvalidPayloadException
     */
    private function __construct(
        public PaymentType $type,
        public ?Money $amount,
        public array $accounts,
        public ?Beneficiary $beneficiary,
        public ?CarbonInterface $dueDate,
        public ?string $variableSymbol,
        public ?string $constantSymbol,
        public ?string $specificSymbol,
        ?string $originatorsReference,
        ?string $note,
        ?string $currency,
        public ?StandingOrderDetails $standingOrder = null,
        public ?DirectDebitDetails $directDebit = null,
    ) {
        if ($amount !== null) {
            if (! Amounts::isIsoCurrency($amount)) {
                throw UnsupportedCurrencyException::notIso(PayBySquare::TYPE);
            }

            if ($currency !== null && ! Amounts::sameCurrency($amount, $currency)) {
                throw UnsupportedCurrencyException::mismatch(PayBySquare::TYPE, 'currency');
            }

            if (! Amounts::fitsBySquareAmount($amount)) {
                throw InvalidPayloadException::outOfRange(PayBySquare::TYPE, 'amount');
            }

            $this->currency = Amounts::currencyCode($amount);
        } else {
            $code = strtoupper(trim((string) $currency));

            if ($code === '') {
                throw InvalidPayloadException::required(PayBySquare::TYPE, 'currency');
            }

            if (preg_match('/^[A-Z]{3}$/', $code) !== 1 || ! Amounts::isIsoCode($code)) {
                throw UnsupportedCurrencyException::notIso(PayBySquare::TYPE, 'currency');
            }

            $this->currency = $code;
        }

        if ($accounts === []) {
            throw InvalidPayloadException::required(PayBySquare::TYPE, 'accounts');
        }

        self::assertSymbol($variableSymbol, 'variableSymbol', 10);
        self::assertSymbol($constantSymbol, 'constantSymbol', 4);
        self::assertSymbol($specificSymbol, 'specificSymbol', 10);
        $this->originatorsReference = self::text($originatorsReference, 'originatorsReference', 35);
        $this->note = self::text($note, 'note', 140);

        $maxAmount = $directDebit?->maxAmount;

        if ($maxAmount !== null && (! Amounts::sameCurrency($maxAmount, $this->currency) || ! Amounts::fitsBySquareAmount($maxAmount))) {
            throw UnsupportedCurrencyException::mismatch(PayBySquare::TYPE, 'directDebit.maxAmount');
        }
    }

    /**
     * @param  list<BankAccount>  $accounts  at least one
     *
     * @throws InvalidPayloadException
     */
    public static function order(
        ?Money $amount,
        array $accounts,
        ?Beneficiary $beneficiary = null,
        ?CarbonInterface $dueDate = null,
        ?string $variableSymbol = null,
        ?string $constantSymbol = null,
        ?string $specificSymbol = null,
        ?string $originatorsReference = null,
        ?string $note = null,
        ?string $currency = null,
    ): self {
        return new self(PaymentType::PaymentOrder, $amount, $accounts, $beneficiary, $dueDate, $variableSymbol, $constantSymbol, $specificSymbol, $originatorsReference, $note, $currency);
    }

    /**
     * @param  list<BankAccount>  $accounts  at least one
     *
     * @throws InvalidPayloadException
     */
    public static function standingOrder(
        StandingOrderDetails $details,
        ?Money $amount,
        array $accounts,
        ?Beneficiary $beneficiary = null,
        ?CarbonInterface $dueDate = null,
        ?string $variableSymbol = null,
        ?string $constantSymbol = null,
        ?string $specificSymbol = null,
        ?string $originatorsReference = null,
        ?string $note = null,
        ?string $currency = null,
    ): self {
        return new self(PaymentType::StandingOrder, $amount, $accounts, $beneficiary, $dueDate, $variableSymbol, $constantSymbol, $specificSymbol, $originatorsReference, $note, $currency, standingOrder: $details);
    }

    /**
     * @param  list<BankAccount>  $accounts  at least one
     *
     * @throws InvalidPayloadException
     */
    public static function directDebit(
        DirectDebitDetails $details,
        ?Money $amount,
        array $accounts,
        ?Beneficiary $beneficiary = null,
        ?CarbonInterface $dueDate = null,
        ?string $variableSymbol = null,
        ?string $constantSymbol = null,
        ?string $specificSymbol = null,
        ?string $originatorsReference = null,
        ?string $note = null,
        ?string $currency = null,
    ): self {
        return new self(PaymentType::DirectDebit, $amount, $accounts, $beneficiary, $dueDate, $variableSymbol, $constantSymbol, $specificSymbol, $originatorsReference, $note, $currency, directDebit: $details);
    }

    /**
     * @internal
     *
     * @throws InvalidPayloadException
     */
    public static function assertSymbol(?string $value, string $field, int $digits): void
    {
        if ($value !== null && preg_match('/^[0-9]{0,'.$digits.'}$/', $value) !== 1) {
            throw InvalidPayloadException::invalidFormat(PayBySquare::TYPE, $field);
        }
    }

    /**
     * @internal
     *
     * @throws InvalidPayloadException
     */
    public static function text(?string $value, string $field, int $max): ?string
    {
        $value = $value === null ? null : trim(TextNormalizer::field($value, PayBySquare::TYPE, $field));

        if ($value === null || $value === '') {
            return null;
        }

        if (mb_strlen($value) > $max) {
            throw InvalidPayloadException::tooLong(PayBySquare::TYPE, $field, $max);
        }

        return $value;
    }
}
