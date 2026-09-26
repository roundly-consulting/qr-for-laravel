<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads;

use Illuminate\Contracts\Translation\Translator;
use RoundlyConsulting\Qr\Contracts\Payload;
use RoundlyConsulting\Qr\DataTransferObjects\PayloadRequirements;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Enums\WifiSecurity;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;
use SensitiveParameter;

/**
 * A Wi-Fi network configuration code (`WIFI:T:WPA;S:ssid;P:password;;`). Special
 * characters (`\ ; , : "`) are backslash-escaped and an all-hex value is quoted so readers
 * do not take it for a hex key. Always a secret: it carries the network password.
 */
final readonly class Wifi implements Payload
{
    /**
     * @throws InvalidPayloadException
     */
    public function __construct(
        public string $ssid,
        #[SensitiveParameter] private ?string $password = null,
        public WifiSecurity $security = WifiSecurity::Wpa,
        public bool $hidden = false,
    ) {
        if ($ssid === '') {
            throw InvalidPayloadException::required('Wifi', 'ssid');
        }

        if (strlen($ssid) > 32) {
            throw InvalidPayloadException::tooLong('Wifi', 'ssid', 32);
        }

        if ($security === WifiSecurity::None) {
            if ($password !== null && $password !== '') {
                throw InvalidPayloadException::mutuallyExclusive('Wifi', 'password', 'security');
            }

            return;
        }

        if ($password === null || $password === '') {
            throw InvalidPayloadException::required('Wifi', 'password');
        }

        if ($security === WifiSecurity::Wpa && mb_strlen($password) < 8) {
            throw InvalidPayloadException::tooShort('Wifi', 'password', 8);
        }

        if ($security === WifiSecurity::Wpa && mb_strlen($password) > 63) {
            throw InvalidPayloadException::tooLong('Wifi', 'password', 63);
        }
    }

    public function toQrString(): string
    {
        $fields = 'T:'.$this->security->value.';S:'.self::escape($this->ssid).';';

        if ($this->security !== WifiSecurity::None) {
            $fields .= 'P:'.self::escape((string) $this->password).';';
        }

        if ($this->hidden) {
            $fields .= 'H:true;';
        }

        return 'WIFI:'.$fields.';';
    }

    public function requirements(): PayloadRequirements
    {
        return new PayloadRequirements;
    }

    public function sensitivity(): Sensitivity
    {
        return Sensitivity::Secret;
    }

    public function description(Translator $translator): string
    {
        return (string) $translator->get('qr::qr.descriptions.wifi');
    }

    private static function escape(string $value): string
    {
        $escaped = (string) preg_replace('/([\\\;,:"])/', '\\\\$1', $value);

        return ctype_xdigit($value) ? '"'.$escaped.'"' : $escaped;
    }
}
