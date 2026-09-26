<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads\Payments\BySquare;

use RoundlyConsulting\Qr\Banking\Bic;
use RoundlyConsulting\Qr\Banking\Iban;
use RoundlyConsulting\Qr\Exceptions\InvalidBicException;
use RoundlyConsulting\Qr\Exceptions\InvalidIbanException;

final readonly class BankAccount
{
    public Iban $iban;

    public ?Bic $bic;

    /**
     * @throws InvalidIbanException
     * @throws InvalidBicException
     */
    public function __construct(string|Iban $iban, string|Bic|null $bic = null)
    {
        $this->iban = $iban instanceof Iban ? $iban : Iban::fromString($iban);
        $this->bic = match (true) {
            $bic instanceof Bic => $bic,
            $bic === null || trim($bic) === '' => null,
            default => Bic::fromString($bic),
        };
    }
}
