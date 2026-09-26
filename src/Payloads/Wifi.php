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
 * do not take it for a hex key. A raw hex key (a 64-digit WPA PSK or a 10/26/58-digit WEP
 * key) is written unquoted only when opted in with {@see self::withHexKey()}. Always a
 * secret: it carries the network password.
 */
final readonly class Wifi implements Payload
{
    /** Hex digits of a raw WEP key (64-, 128- and 256-bit WEP). */
    public const array WEP_HEX_KEY_LENGTHS = [10, 26, 58];

    /** Hex digits of a raw WPA pre-shared key. */
    public const int WPA_HEX_KEY_LENGTH = 64;

    /**
     * @param  bool  $hexKey  the password is a raw hex key, written unquoted (see {@see self::withHexKey()})
     *
     * @throws InvalidPayloadException
     */
    public function __construct(
        public string $ssid,
        #[SensitiveParameter] private ?string $password = null,
        public WifiSecurity $security = WifiSecurity::Wpa,
        public bool $hidden = false,
        public bool $hexKey = false,
    ) {
        if ($ssid === '') {
            throw InvalidPayloadException::required('Wifi', 'ssid');
        }

        if (strlen($ssid) > 32) {
            throw InvalidPayloadException::tooLong('Wifi', 'ssid', 32);
        }

        if ($hexKey) {
            self::assertHexKey((string) $password, $security);

            return;
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

    /**
     * A network secured with a raw hex key instead of a passphrase: exactly 64 hex digits for
     * WPA, 10, 26 or 58 for WEP. The key is written unquoted, so readers take it as hex.
     *
     * @throws InvalidPayloadException
     */
    public static function withHexKey(string $ssid, #[SensitiveParameter] string $key, WifiSecurity $security = WifiSecurity::Wpa, bool $hidden = false): self
    {
        return new self($ssid, $key, $security, $hidden, hexKey: true);
    }

    public function toQrString(): string
    {
        $fields = 'T:'.$this->security->value.';S:'.self::escape($this->ssid).';';

        if ($this->hexKey) {
            $fields .= 'P:'.$this->password.';';
        } elseif ($this->security !== WifiSecurity::None) {
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

    /**
     * @throws InvalidPayloadException
     */
    private static function assertHexKey(#[SensitiveParameter] string $key, WifiSecurity $security): void
    {
        $lengths = match ($security) {
            WifiSecurity::Wpa => [self::WPA_HEX_KEY_LENGTH],
            WifiSecurity::Wep => self::WEP_HEX_KEY_LENGTHS,
            default => throw InvalidPayloadException::mutuallyExclusive('Wifi', 'hexKey', 'security'),
        };

        if (! ctype_xdigit($key) || ! in_array(strlen($key), $lengths, true)) {
            throw InvalidPayloadException::invalidFormat('Wifi', 'password');
        }
    }

    private static function escape(string $value): string
    {
        $escaped = (string) preg_replace('/([\\\;,:"])/', '\\\\$1', $value);

        return ctype_xdigit($value) ? '"'.$escaped.'"' : $escaped;
    }
}
