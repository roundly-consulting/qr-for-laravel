<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Facades;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Qr\Contracts\QrFactory;
use RoundlyConsulting\Qr\QrManager;

/**
 * @method static \RoundlyConsulting\Qr\PendingQr make(string|\RoundlyConsulting\Qr\Contracts\Payload $data)
 * @method static \RoundlyConsulting\Qr\PendingQr text(string $text)
 * @method static \RoundlyConsulting\Qr\PendingQr url(string $url, list<string> $schemes = ['http', 'https'])
 * @method static \RoundlyConsulting\Qr\PendingQr email(string $to, ?string $subject = null, ?string $body = null)
 * @method static \RoundlyConsulting\Qr\PendingQr phone(string $number)
 * @method static \RoundlyConsulting\Qr\PendingQr sms(string $number, ?string $message = null, \RoundlyConsulting\Qr\Enums\SmsFormat $format = \RoundlyConsulting\Qr\Enums\SmsFormat::Smsto)
 * @method static \RoundlyConsulting\Qr\PendingQr wifi(string $ssid, ?string $password = null, \RoundlyConsulting\Qr\Enums\WifiSecurity $security = \RoundlyConsulting\Qr\Enums\WifiSecurity::Wpa, bool $hidden = false)
 * @method static \RoundlyConsulting\Qr\PendingQr vcard(\RoundlyConsulting\Qr\Payloads\VCard $card)
 * @method static \RoundlyConsulting\Qr\PendingQr geo(float $latitude, float $longitude)
 * @method static \RoundlyConsulting\Qr\PendingQr otpauth(string|\RoundlyConsulting\Qr\Payloads\Otpauth $uriOrPayload)
 * @method static \RoundlyConsulting\Qr\PendingQr epc(\RoundlyConsulting\Qr\Payloads\Payments\Epc\EpcPayment $payment)
 * @method static \RoundlyConsulting\Qr\ValueObjects\Svg svg(string|\RoundlyConsulting\Qr\Contracts\Payload $data, ?\RoundlyConsulting\Qr\DataTransferObjects\QrOptions $options = null)
 * @method static \RoundlyConsulting\Qr\ValueObjects\QrMatrix matrix(string|\RoundlyConsulting\Qr\Contracts\Payload $data, ?\RoundlyConsulting\Qr\DataTransferObjects\QrOptions $options = null)
 *
 * @see QrManager
 */
final class Qr extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return QrFactory::class;
    }
}
