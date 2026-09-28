<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Contracts;

use RoundlyConsulting\Qr\DataTransferObjects\QrOptions;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\Segmentation;
use RoundlyConsulting\Qr\Enums\SmsFormat;
use RoundlyConsulting\Qr\Enums\WifiSecurity;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;
use RoundlyConsulting\Qr\Payloads\Otpauth;
use RoundlyConsulting\Qr\Payloads\Payments\BySquare\PayBySquare;
use RoundlyConsulting\Qr\Payloads\Payments\Epc\EpcPayment;
use RoundlyConsulting\Qr\Payloads\Text;
use RoundlyConsulting\Qr\Payloads\VCard;
use RoundlyConsulting\Qr\PendingQr;
use RoundlyConsulting\Qr\ValueObjects\EncodingInfo;
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

    /**
     * A PAY by square code; unset version/deburr fields take the configured defaults.
     */
    public function payBySquare(PayBySquare $document): PendingQr;

    public function svg(string|Payload $data, ?QrOptions $options = null): Svg;

    public function matrix(string|Payload $data, ?QrOptions $options = null): QrMatrix;

    /**
     * How the data would be encoded — version, level, mask, segments, bit budget — without
     * rendering. Shortcut for `make($data)->withOptions($options)->info()`.
     */
    public function info(string|Payload $data, ?QrOptions $options = null): EncodingInfo;

    /**
     * Whether `make($data)` fits in a symbol no larger than `$maxVersion` at `$level`. Unset
     * arguments take what the payload requires, then the configuration — the settings the
     * data would really be encoded with — so a passing check never meets a
     * DataTooLongException later. Payload-locked options still throw when overridden.
     *
     * @throws InvalidOptionException for an out-of-range `$maxVersion` or an override of a
     *                                payload-locked level/segmentation
     */
    public function fits(string|Payload $data, ?ErrorCorrection $level = null, ?int $maxVersion = null, ?Segmentation $segmentation = null): bool;
}
