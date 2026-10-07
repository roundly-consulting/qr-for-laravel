<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads;

use Illuminate\Contracts\Translation\Translator;
use RoundlyConsulting\Crypto\Codec\Base32;
use RoundlyConsulting\Crypto\Codec\InvalidEncodingException;
use RoundlyConsulting\Crypto\Otp\OtpAlgorithm;
use RoundlyConsulting\Crypto\Otp\ProvisioningUri;
use RoundlyConsulting\Qr\Contracts\Payload;
use RoundlyConsulting\Qr\DataTransferObjects\PayloadRequirements;
use RoundlyConsulting\Qr\Enums\OtpType;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;
use SensitiveParameter;

/**
 * A 2FA enrolment code in the Key URI format (`otpauth://totp/Issuer:account?secret=…`).
 *
 * `fromUri()` keeps the given URI byte for byte, so a URI built by another package is
 * never altered; `totp()` builds it with the same provisioning-URI builder two-factor
 * uses. Always a secret, and locked there: the seed must never be cached or served
 * cacheably. Error messages name fields, never the secret or any other URI part.
 */
final readonly class Otpauth implements Payload
{
    public const int MAX_LENGTH = 1024;

    private function __construct(
        #[SensitiveParameter] private string $uri,
        public OtpType $type,
        public string $issuer,
        public string $account,
    ) {}

    /**
     * @throws InvalidPayloadException
     */
    public static function fromUri(#[SensitiveParameter] string $uri): self
    {
        if (strlen($uri) > self::MAX_LENGTH) {
            throw InvalidPayloadException::tooLong('Otpauth', 'uri', self::MAX_LENGTH);
        }

        if (preg_match('#^otpauth://([A-Za-z]+)/([^?]*)(?:\?(.*))?$#si', $uri, $parts) !== 1) {
            throw InvalidPayloadException::invalidFormat('Otpauth', 'uri');
        }

        $type = OtpType::tryFrom(strtolower($parts[1])) ?? throw InvalidPayloadException::invalidFormat('Otpauth', 'type');
        $query = self::query($parts[3] ?? '');
        [$labelIssuer, $account] = self::splitLabel($parts[2]);

        if ($account === '') {
            throw InvalidPayloadException::required('Otpauth', 'account');
        }

        self::assertSecret($query['secret'] ?? '');

        if (isset($query['algorithm']) && OtpAlgorithm::tryFrom(strtolower($query['algorithm'])) === null) {
            throw InvalidPayloadException::invalidFormat('Otpauth', 'algorithm');
        }

        if (isset($query['digits'])) {
            self::assertIntegerBetween($query['digits'], 'digits', 6, 10);
        }

        if (isset($query['period'])) {
            self::assertIntegerBetween($query['period'], 'period', 1, PHP_INT_MAX);
        }

        if ($type === OtpType::Hotp) {
            self::assertIntegerBetween($query['counter'] ?? throw InvalidPayloadException::required('Otpauth', 'counter'), 'counter', 0, PHP_INT_MAX);
        }

        return new self($uri, $type, $labelIssuer ?? $query['issuer'] ?? '', $account);
    }

    /**
     * @throws InvalidPayloadException
     */
    public static function totp(
        #[SensitiveParameter] string $secret,
        string $account,
        string $issuer,
        OtpAlgorithm $algorithm = OtpAlgorithm::Sha1,
        int $digits = 6,
        int $period = 30,
    ): self {
        self::assertBuildable($secret, $account, $issuer, $digits);

        if ($period < 1) {
            throw InvalidPayloadException::outOfRange('Otpauth', 'period');
        }

        return new self(ProvisioningUri::totp($secret, $account, $issuer, $algorithm, $digits, $period), OtpType::Totp, $issuer, $account);
    }

    /**
     * Counter-based codes, encoded with the same rules as {@see ProvisioningUri::totp()}
     * plus the `counter` parameter.
     *
     * @throws InvalidPayloadException
     */
    public static function hotp(
        #[SensitiveParameter] string $secret,
        string $account,
        string $issuer,
        int $counter,
        OtpAlgorithm $algorithm = OtpAlgorithm::Sha1,
        int $digits = 6,
    ): self {
        self::assertBuildable($secret, $account, $issuer, $digits);

        if ($counter < 0) {
            throw InvalidPayloadException::outOfRange('Otpauth', 'counter');
        }

        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper($algorithm->value),
            'digits' => $digits,
            'counter' => $counter,
        ], '', '&', PHP_QUERY_RFC3986);

        $uri = sprintf('otpauth://hotp/%s:%s?%s', rawurlencode($issuer), rawurlencode($account), $query);

        return new self($uri, OtpType::Hotp, $issuer, $account);
    }

    public function type(): OtpType
    {
        return $this->type;
    }

    public function issuer(): string
    {
        return $this->issuer;
    }

    public function account(): string
    {
        return $this->account;
    }

    public function toQrString(): string
    {
        return $this->uri;
    }

    public function requirements(): PayloadRequirements
    {
        return new PayloadRequirements(locked: [PayloadRequirements::SENSITIVITY]);
    }

    public function sensitivity(): Sensitivity
    {
        return Sensitivity::Secret;
    }

    public function description(Translator $translator): string
    {
        return (string) $translator->get('qr::qr.descriptions.otpauth');
    }

    /**
     * The issuer may itself contain ':' once decoded ("Acme: Admin" arrives as
     * `Acme%3A%20Admin:user`), so the still-encoded label is split on its first literal
     * colon — or, for hand-written URIs without one, on the first `%3A`.
     *
     * @return array{0: ?string, 1: string} issuer from the label (null when absent), account
     */
    private static function splitLabel(string $label): array
    {
        $position = strpos($label, ':');
        $separatorLength = 1;

        if ($position === false) {
            $position = stripos($label, '%3A');
            $separatorLength = 3;
        }

        if ($position === false) {
            return [null, rawurldecode($label)];
        }

        return [rawurldecode(substr($label, 0, $position)), rawurldecode(substr($label, $position + $separatorLength))];
    }

    /**
     * @return array<string, string>
     */
    private static function query(string $query): array
    {
        $values = [];

        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $values[strtolower(rawurldecode($key))] = rawurldecode($value);
        }

        return $values;
    }

    private static function assertSecret(#[SensitiveParameter] string $secret): void
    {
        if ($secret === '') {
            throw InvalidPayloadException::required('Otpauth', 'secret');
        }

        try {
            Base32::decode($secret);
        } catch (InvalidEncodingException) {
            throw InvalidPayloadException::invalidFormat('Otpauth', 'secret');
        }
    }

    private static function assertBuildable(#[SensitiveParameter] string $secret, string $account, string $issuer, int $digits): void
    {
        self::assertSecret($secret);

        if ($account === '') {
            throw InvalidPayloadException::required('Otpauth', 'account');
        }

        if ($issuer === '') {
            throw InvalidPayloadException::required('Otpauth', 'issuer');
        }

        if ($digits < 6 || $digits > 10) {
            throw InvalidPayloadException::outOfRange('Otpauth', 'digits');
        }
    }

    private static function assertIntegerBetween(string $value, string $field, int $min, int $max): void
    {
        if (preg_match('/^\d{1,18}\z/', $value) !== 1 || (int) $value < $min || (int) $value > $max) {
            throw InvalidPayloadException::outOfRange('Otpauth', $field);
        }
    }
}
