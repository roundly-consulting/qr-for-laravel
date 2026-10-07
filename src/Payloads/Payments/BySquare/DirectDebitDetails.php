<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads\Payments\BySquare;

use Carbon\CarbonInterface;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Qr\Enums\BySquare\DirectDebitScheme;
use RoundlyConsulting\Qr\Enums\BySquare\DirectDebitType;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;
use RoundlyConsulting\Qr\Support\TextNormalizer;

/**
 * The direct-debit extension. Scheme and type are optional and serialise as empty fields
 * when unset.
 */
final readonly class DirectDebitDetails
{
    public ?string $originatorsReference;

    /**
     * @throws InvalidPayloadException
     */
    public function __construct(
        public ?DirectDebitScheme $scheme = null,
        public ?DirectDebitType $type = null,
        public ?string $variableSymbol = null,
        public ?string $specificSymbol = null,
        ?string $originatorsReference = null,
        public ?string $mandateId = null,
        public ?string $creditorId = null,
        public ?string $contractId = null,
        public ?Money $maxAmount = null,
        public ?CarbonInterface $validTill = null,
    ) {
        Payment::assertSymbol($variableSymbol, 'directDebit.variableSymbol', 10);
        Payment::assertSymbol($specificSymbol, 'directDebit.specificSymbol', 10);
        $this->originatorsReference = Payment::text($originatorsReference, 'directDebit.originatorsReference', 35);

        foreach (['mandateId' => $mandateId, 'creditorId' => $creditorId, 'contractId' => $contractId] as $field => $value) {
            if ($value !== null && mb_strlen(TextNormalizer::field($value, PayBySquare::TYPE, 'directDebit.'.$field)) > 35) {
                throw InvalidPayloadException::tooLong(PayBySquare::TYPE, 'directDebit.'.$field, 35);
            }
        }
    }
}
