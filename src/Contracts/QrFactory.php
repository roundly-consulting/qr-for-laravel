<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Contracts;

use RoundlyConsulting\Qr\DataTransferObjects\QrOptions;
use RoundlyConsulting\Qr\Enums\SmsFormat;
use RoundlyConsulting\Qr\Enums\WifiSecurity;
use RoundlyConsulting\Qr\Payloads\Otpauth;
use RoundlyConsulting\Qr\Payloads\Payments\Epc\EpcPayment;
use RoundlyConsulting\Qr\Payloads\Text;
use RoundlyConsulting\Qr\Payloads\VCard;
use RoundlyConsulting\Qr\PendingQr;
use RoundlyConsulting\Qr\ValueObjects\QrMatrix;
use RoundlyConsulting\Qr\ValueObjects\Svg;
use SensitiveParameter;

interface QrFactory
{
    /**
     * A raw string always becomes a Text payload (secret-looking text is still treated as a
     * secret — see {@see Text}).
     */
    public function make(string|Payload $data): PendingQr;

    public function text(string $text): PendingQr;

    /**
     * @param  list<string>  $schemes
     */
    public function url(string $url, array $schemes = ['http', 'https']): PendingQr;

    public function email(string $to, ?string $subject = null, ?string $body = null): PendingQr;

    public function phone(string $number): PendingQr;

    public function sms(string $number, ?string $message = null, SmsFormat $format = SmsFormat::Smsto): PendingQr;

    public function wifi(string $ssid, #[SensitiveParameter] ?string $password = null, WifiSecurity $security = WifiSecurity::Wpa, bool $hidden = false): PendingQr;

    public function vcard(VCard $card): PendingQr;

    public function geo(float $latitude, float $longitude): PendingQr;

    public function otpauth(#[SensitiveParameter] string|Otpauth $uriOrPayload): PendingQr;

    /**
     * A SEPA credit transfer code; unset version/charset fields take the configured defaults.
     */
    public function epc(EpcPayment $payment): PendingQr;

    public function svg(string|Payload $data, ?QrOptions $options = null): Svg;

    public function matrix(string|Payload $data, ?QrOptions $options = null): QrMatrix;
}
