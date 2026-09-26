<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads\Payments\BySquare;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Qr\Enums\BySquare\BySquareVersion;
use RoundlyConsulting\Qr\Enums\BySquare\DirectDebitScheme;
use RoundlyConsulting\Qr\Enums\BySquare\DirectDebitType;
use RoundlyConsulting\Qr\Enums\BySquare\Month;
use RoundlyConsulting\Qr\Enums\BySquare\PaymentType;
use RoundlyConsulting\Qr\Enums\BySquare\Periodicity;
use RoundlyConsulting\Qr\Exceptions\BySquareDecodeException;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;
use RoundlyConsulting\Qr\Support\Amounts;
use RoundlyConsulting\Qr\Support\TextNormalizer;
use Throwable;

/**
 * The tab-separated PAY by square data model (by square specification, Table 15):
 *
 *   invoiceId, paymentsCount,
 *   per payment: type, amount, currency, due date, VS, KS, SS, originator's reference, note,
 *                accountsCount, per account: IBAN, BIC,
 *                standing-order flag [day, month mask, periodicity, last date],
 *                direct-debit flag [scheme, type, VS, SS, originator's reference, mandate,
 *                                   creditor id, contract id, max amount, valid till],
 *   then, from 1.1.0, per payment: beneficiary name, street, city.
 *
 * Absent values are empty fields; dates are Ymd calendar dates in the value's own timezone.
 *
 * @internal
 */
final class BySquareSerializer
{
    public static function serialize(PayBySquare $document, BySquareVersion $version, bool $deburr): string
    {
        $text = static fn (?string $value, bool $deburrable = false): string => $value === null
            ? ''
            : ($deburr && $deburrable ? Deburr::apply(TextNormalizer::clean($value)) : TextNormalizer::clean($value));

        $fields = [$text($document->invoiceId), (string) count($document->payments)];

        foreach ($document->payments as $payment) {
            array_push(
                $fields,
                (string) $payment->type->value,
                $payment->amount === null ? '' : Amounts::decimal($payment->amount),
                $payment->currency,
                self::date($payment->dueDate),
                $payment->variableSymbol ?? '',
                $payment->constantSymbol ?? '',
                $payment->specificSymbol ?? '',
                $text($payment->originatorsReference),
                $text($payment->note, true),
                (string) count($payment->accounts),
            );

            foreach ($payment->accounts as $account) {
                array_push($fields, $account->iban->electronic(), $account->bic?->value() ?? '');
            }

            $standing = $payment->standingOrder;

            if ($standing === null) {
                $fields[] = '0';
            } else {
                array_push(
                    $fields,
                    '1',
                    $standing->day === null ? '' : (string) $standing->day,
                    $standing->months === [] ? '' : (string) Month::mask(...$standing->months),
                    $standing->periodicity->value,
                    self::date($standing->lastDate),
                );
            }

            $debit = $payment->directDebit;

            if ($debit === null) {
                $fields[] = '0';
            } else {
                array_push(
                    $fields,
                    '1',
                    $debit->scheme === null ? '' : (string) $debit->scheme->value,
                    $debit->type === null ? '' : (string) $debit->type->value,
                    $debit->variableSymbol ?? '',
                    $debit->specificSymbol ?? '',
                    $text($debit->originatorsReference),
                    $text($debit->mandateId),
                    $text($debit->creditorId),
                    $text($debit->contractId),
                    $debit->maxAmount === null ? '' : Amounts::decimal($debit->maxAmount),
                    self::date($debit->validTill),
                );
            }
        }

        if ($version->hasBeneficiaryBlock()) {
            foreach ($document->payments as $payment) {
                array_push(
                    $fields,
                    $text($payment->beneficiary?->name, true),
                    $text($payment->beneficiary?->street, true),
                    $text($payment->beneficiary?->city, true),
                );
            }
        }

        return implode("\t", $fields);
    }

    /**
     * @throws BySquareDecodeException
     */
    public static function unserialize(string $payload, BySquareVersion $version): PayBySquare
    {
        $fields = explode("\t", $payload);
        $cursor = 0;
        $next = static function () use (&$cursor, $fields): string {
            if (! array_key_exists($cursor, $fields)) {
                throw BySquareDecodeException::fields();
            }

            return $fields[$cursor++];
        };

        try {
            $invoiceId = self::nullable($next());
            $count = self::integer($next(), 'paymentsCount', 1, 99);
            $parsed = [];

            for ($i = 0; $i < $count; $i++) {
                $parsed[] = self::payment($next);
            }

            $beneficiaries = [];

            // 1.0.0 predates the beneficiary block, but encoders in circulation write it for
            // every version: accept it when exactly one name/street/city triple per payment
            // follows. A 1.0.0 code that actually names a beneficiary decodes as 1.1.0, the
            // first version able to carry it.
            if ($version->hasBeneficiaryBlock() || count($fields) - $cursor === 3 * $count) {
                for ($i = 0; $i < $count; $i++) {
                    [$name, $street, $city] = [$next(), $next(), $next()];
                    $beneficiaries[$i] = $name === '' ? null : new Beneficiary($name, self::nullable($street), self::nullable($city));
                }
            }

            if ($cursor !== count($fields)) {
                throw BySquareDecodeException::fields();
            }

            $payments = [];

            foreach ($parsed as $i => $payment) {
                $payments[] = $payment($beneficiaries[$i] ?? null);
            }

            if ($payments === []) {
                throw BySquareDecodeException::fields();
            }

            if (! $version->hasBeneficiaryBlock() && array_filter($beneficiaries) !== []) {
                $version = BySquareVersion::V1_1_0;
            }

            return new PayBySquare($payments, $invoiceId, $version, false);
        } catch (InvalidPayloadException $e) {
            throw BySquareDecodeException::value((string) $e->field, $e);
        }
    }

    /**
     * Read one payment block; returns a builder awaiting the beneficiary (which comes last).
     *
     * @param  callable(): string  $next
     * @return callable(?Beneficiary): Payment
     */
    private static function payment(callable $next): callable
    {
        $type = PaymentType::tryFrom(self::integer($next(), 'type', 1, 4)) ?? throw BySquareDecodeException::value('type');
        [$amount, $currency] = [$next(), $next()];
        $money = $amount === '' ? null : self::money($amount, $currency, 'amount');
        $dueDate = self::parseDate($next(), 'dueDate');
        [$vs, $ks, $ss, $reference, $note] = [$next(), $next(), $next(), $next(), $next()];
        $accountCount = self::integer($next(), 'accountsCount', 1, 99);
        $accounts = [];

        for ($i = 0; $i < $accountCount; $i++) {
            [$iban, $bic] = [$next(), $next()];
            $accounts[] = new BankAccount($iban, self::nullable($bic));
        }

        $standing = null;

        if (self::flag($next(), 'standingOrderExt')) {
            [$day, $mask, $periodicity, $lastDate] = [$next(), $next(), $next(), $next()];
            $standing = new StandingOrderDetails(
                Periodicity::tryFrom($periodicity) ?? throw BySquareDecodeException::value('periodicity'),
                $day === '' ? null : self::integer($day, 'day', 1, 31),
                $mask === '' ? [] : Month::fromMask(self::integer($mask, 'month', 1, 4095)),
                self::parseDate($lastDate, 'lastDate'),
            );
        }

        $debit = null;

        if (self::flag($next(), 'directDebitExt')) {
            $fields = [];

            for ($i = 0; $i < 10; $i++) {
                $fields[] = $next();
            }

            $debit = new DirectDebitDetails(
                $fields[0] === '' ? null : (DirectDebitScheme::tryFrom(self::integer($fields[0], 'directDebitScheme', 0, 1)) ?? throw BySquareDecodeException::value('directDebitScheme')),
                $fields[1] === '' ? null : (DirectDebitType::tryFrom(self::integer($fields[1], 'directDebitType', 0, 1)) ?? throw BySquareDecodeException::value('directDebitType')),
                self::nullable($fields[2]),
                self::nullable($fields[3]),
                self::nullable($fields[4]),
                self::nullable($fields[5]),
                self::nullable($fields[6]),
                self::nullable($fields[7]),
                $fields[8] === '' ? null : self::money($fields[8], $currency, 'maxAmount'),
                self::parseDate($fields[9], 'validTill'),
            );
        }

        $vs = self::nullable($vs);
        $ks = self::nullable($ks);
        $ss = self::nullable($ss);
        $reference = self::nullable($reference);
        $note = self::nullable($note);
        $code = $currency === '' ? null : $currency;

        if ($accounts === []) {
            throw BySquareDecodeException::value('accountsCount');
        }

        return static fn (?Beneficiary $beneficiary): Payment => match ($type) {
            PaymentType::PaymentOrder => Payment::order($money, $accounts, $beneficiary, $dueDate, $vs, $ks, $ss, $reference, $note, $code),
            PaymentType::StandingOrder => Payment::standingOrder($standing ?? throw BySquareDecodeException::value('standingOrderExt'), $money, $accounts, $beneficiary, $dueDate, $vs, $ks, $ss, $reference, $note, $code),
            PaymentType::DirectDebit => Payment::directDebit($debit ?? throw BySquareDecodeException::value('directDebitExt'), $money, $accounts, $beneficiary, $dueDate, $vs, $ks, $ss, $reference, $note, $code),
        };
    }

    private static function date(?CarbonInterface $date): string
    {
        return $date === null ? '' : $date->format('Ymd');
    }

    private static function parseDate(string $value, string $field): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }

        $date = preg_match('/^[0-9]{8}$/', $value) === 1 ? CarbonImmutable::createFromFormat('!Ymd', $value) : null;

        // Reject rolled-over dates such as 20260230.
        if (! $date instanceof CarbonImmutable || $date->format('Ymd') !== $value) {
            throw BySquareDecodeException::value($field);
        }

        return $date;
    }

    private static function money(string $amount, string $currency, string $field): Money
    {
        if (preg_match('/^[0-9]{1,15}(\.[0-9]+)?$/', $amount) !== 1 || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw BySquareDecodeException::value($field);
        }

        try {
            return Amounts::fromDecimal($amount, $currency);
        } catch (Throwable $e) {
            throw BySquareDecodeException::value($field, $e);
        }
    }

    private static function integer(string $value, string $field, int $min, int $max): int
    {
        if (preg_match('/^[0-9]{1,9}$/', $value) !== 1 || (int) $value < $min || (int) $value > $max) {
            throw BySquareDecodeException::value($field);
        }

        return (int) $value;
    }

    private static function flag(string $value, string $field): bool
    {
        return match ($value) {
            '0' => false,
            '1' => true,
            default => throw BySquareDecodeException::value($field),
        };
    }

    private static function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
