<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr;

use Closure;
use Illuminate\Contracts\Translation\Translator;
use RoundlyConsulting\Crypto\Hash\Digest;
use RoundlyConsulting\Qr\Contracts\Payload;
use RoundlyConsulting\Qr\Contracts\QrFactory;
use RoundlyConsulting\Qr\DataTransferObjects\EncodeOptions;
use RoundlyConsulting\Qr\DataTransferObjects\QrOptions;
use RoundlyConsulting\Qr\DataTransferObjects\SvgOptions;
use RoundlyConsulting\Qr\Encoder\Encoder;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\Segmentation;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Enums\SmsFormat;
use RoundlyConsulting\Qr\Enums\WifiSecurity;
use RoundlyConsulting\Qr\Payloads\Email;
use RoundlyConsulting\Qr\Payloads\Geo;
use RoundlyConsulting\Qr\Payloads\Otpauth;
use RoundlyConsulting\Qr\Payloads\Payments\BySquare\PayBySquare;
use RoundlyConsulting\Qr\Payloads\Payments\Epc\EpcPayment;
use RoundlyConsulting\Qr\Payloads\Phone;
use RoundlyConsulting\Qr\Payloads\Sms;
use RoundlyConsulting\Qr\Payloads\Text;
use RoundlyConsulting\Qr\Payloads\Url;
use RoundlyConsulting\Qr\Payloads\VCard;
use RoundlyConsulting\Qr\Payloads\Wifi;
use RoundlyConsulting\Qr\Support\ConfigGuard;
use RoundlyConsulting\Qr\Support\MatrixMemo;
use RoundlyConsulting\Qr\Support\SvgCache;
use RoundlyConsulting\Qr\Svg\SvgRenderer;
use RoundlyConsulting\Qr\ValueObjects\EncodingInfo;
use RoundlyConsulting\Qr\ValueObjects\QrMatrix;
use RoundlyConsulting\Qr\ValueObjects\Svg;
use SensitiveParameter;

/**
 * The facade root: builds {@see PendingQr} instances and owns the encoder, renderer, memo
 * and cache they share.
 */
final readonly class QrManager implements QrFactory
{
    public function __construct(
        private Encoder $encoder,
        private SvgRenderer $renderer,
        private MatrixMemo $memo,
        private SvgCache $cache,
        private Translator $translator,
    ) {}

    /**
     * Payment payloads get the configured EPC / PAY by square defaults for any option they
     * leave unset, whichever entry point they come through (facade, options form, Blade).
     */
    public function make(string|Payload $data): PendingQr
    {
        $payload = match (true) {
            is_string($data) => new Text($data),
            $data instanceof EpcPayment => $data->withDefaults(ConfigGuard::epcVersion(), ConfigGuard::epcCharset(), ConfigGuard::epcStrictCharset()),
            $data instanceof PayBySquare => $data->withDefaults(ConfigGuard::bySquareVersion(), ConfigGuard::bySquareDeburr()),
            default => $data,
        };

        return new PendingQr($this, $payload);
    }

    public function text(string $text): PendingQr
    {
        return $this->make(new Text($text));
    }

    public function url(string $url, array $schemes = ['http', 'https']): PendingQr
    {
        return $this->make(new Url($url, $schemes));
    }

    public function email(string $to, ?string $subject = null, ?string $body = null): PendingQr
    {
        return $this->make(new Email($to, $subject, $body));
    }

    public function phone(string $number): PendingQr
    {
        return $this->make(new Phone($number));
    }

    public function sms(string $number, ?string $message = null, SmsFormat $format = SmsFormat::Smsto): PendingQr
    {
        return $this->make(new Sms($number, $message, $format));
    }

    public function wifi(string $ssid, #[SensitiveParameter] ?string $password = null, WifiSecurity $security = WifiSecurity::Wpa, bool $hidden = false): PendingQr
    {
        return $this->make(new Wifi($ssid, $password, $security, $hidden));
    }

    public function vcard(VCard $card): PendingQr
    {
        return $this->make($card);
    }

    public function geo(float $latitude, float $longitude): PendingQr
    {
        return $this->make(new Geo($latitude, $longitude));
    }

    public function otpauth(#[SensitiveParameter] string|Otpauth $uriOrPayload): PendingQr
    {
        return $this->make(is_string($uriOrPayload) ? Otpauth::fromUri($uriOrPayload) : $uriOrPayload);
    }

    public function epc(EpcPayment $payment): PendingQr
    {
        return $this->make($payment);
    }

    public function payBySquare(PayBySquare $document): PendingQr
    {
        return $this->make($document);
    }

    public function svg(string|Payload $data, ?QrOptions $options = null): Svg
    {
        return $this->withOptions($data, $options)->svg();
    }

    public function matrix(string|Payload $data, ?QrOptions $options = null): QrMatrix
    {
        return $this->withOptions($data, $options)->matrix();
    }

    public function info(string|Payload $data, ?QrOptions $options = null): EncodingInfo
    {
        return $this->withOptions($data, $options)->info();
    }

    public function fits(string|Payload $data, ?ErrorCorrection $level = null, ?int $maxVersion = null, ?Segmentation $segmentation = null): bool
    {
        $pending = $this->make($data);

        if ($level !== null) {
            $pending = $pending->errorCorrection($level);
        }

        if ($segmentation !== null) {
            $pending = $pending->segmentation($segmentation);
        }

        // The question is about the ceiling: open the window from version 1 so a configured
        // minimum above `$maxVersion` cannot turn a fit into an invalid range.
        if ($maxVersion !== null) {
            $pending = $pending->versions(1, $maxVersion);
        }

        return $pending->fits();
    }

    /**
     * The translator PendingQr reads its default title and payload descriptions from.
     *
     * @internal
     */
    public function translator(): Translator
    {
        return $this->translator;
    }

    /**
     * Whether the encoder finds a version for the data under the resolved options — no
     * symbol is built, and nothing goes through the memo or the cache.
     *
     * @internal
     */
    public function encoderFits(string $data, EncodeOptions $options): bool
    {
        return $this->encoder->fits($data, $options);
    }

    /**
     * Encode, going through the memo only for public payloads.
     *
     * @internal
     */
    public function encode(string $data, EncodeOptions $options, Sensitivity $sensitivity): QrMatrix
    {
        $encode = fn (): QrMatrix => $this->encoder->encode($data, $options);

        if (! $sensitivity->isCacheable()) {
            return $encode();
        }

        return $this->memo->remember((new Digest)->hex($data."\0".self::fingerprint($options)), $encode);
    }

    /**
     * Render, going through the optional SVG cache only for public payloads.
     *
     * @param  Closure(): QrMatrix  $matrix
     *
     * @internal
     */
    public function render(string $data, Closure $matrix, EncodeOptions $encode, SvgOptions $svg, string $title, ?string $description, Sensitivity $sensitivity): Svg
    {
        $render = fn (): Svg => $this->renderer->render($matrix(), $svg, $title, $description, $sensitivity);

        if (! $sensitivity->isCacheable()) {
            return $render();
        }

        $fingerprint = implode("\0", [$data, self::fingerprint($encode), self::fingerprint($svg), $title, $description ?? '']);

        return $this->cache->remember($fingerprint, $render, $matrix);
    }

    private function withOptions(string|Payload $data, ?QrOptions $options): PendingQr
    {
        $pending = $this->make($data);

        return $options === null ? $pending : $pending->withOptions($options);
    }

    private static function fingerprint(EncodeOptions|SvgOptions $options): string
    {
        return (string) json_encode(get_object_vars($options) + ['class' => $options::class], JSON_THROW_ON_ERROR);
    }
}
