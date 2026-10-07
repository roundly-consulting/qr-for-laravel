<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads;

use Illuminate\Contracts\Translation\Translator;
use RoundlyConsulting\Qr\Contracts\Payload;
use RoundlyConsulting\Qr\DataTransferObjects\PayloadRequirements;
use RoundlyConsulting\Qr\Enums\Segmentation;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;
use RoundlyConsulting\Qr\Support\SecretContent;

/**
 * A link. The scheme must be on the allow-list (http and https by default), so a code
 * never carries `javascript:`, `data:` or `file:` unless a host opts in. Non-ASCII paths
 * and internationalised hosts are accepted as given.
 *
 * An opted-in `otpauth`, `otpauth-migration` or `wifi` URL carries a 2FA seed or a Wi-Fi
 * password, so it gets the same treatment as the raw string would through {@see Text}:
 * Secret (never memoised, cached or served shareable), locked for the otpauth forms.
 */
final readonly class Url implements Payload
{
    public const int MAX_LENGTH = 4096;

    /**
     * The WHATWG URL Standard's special schemes, whose authority a backslash ends.
     */
    private const array SPECIAL_SCHEMES = ['ftp', 'file', 'http', 'https', 'ws', 'wss'];

    public string $scheme;

    public string $host;

    /**
     * @param  list<string>  $schemes
     *
     * @throws InvalidPayloadException
     */
    public function __construct(public string $url, array $schemes = ['http', 'https'])
    {
        if ($url === '') {
            throw InvalidPayloadException::required('Url', 'url');
        }

        if (strlen($url) > self::MAX_LENGTH) {
            throw InvalidPayloadException::tooLong('Url', 'url', self::MAX_LENGTH);
        }

        $parts = preg_match('/[\x00-\x20\x7F]/', $url) === 1 ? false : parse_url($url);

        if ($parts === false || ! isset($parts['scheme'])) {
            throw InvalidPayloadException::invalidFormat('Url', 'url');
        }

        $this->scheme = strtolower($parts['scheme']);

        if (! in_array($this->scheme, array_map(strtolower(...), $schemes), true)) {
            throw InvalidPayloadException::unsupportedScheme('Url', 'url');
        }

        // parse_url() is not multibyte-safe for hosts, so the host is read from the raw
        // authority: drop user info, then a port or IPv6 brackets.
        $authority = preg_match('#^[^:/?\#]+://([^/?\#]*)#', $url, $match) === 1 ? $match[1] : '';

        // Browsers (the WHATWG URL Standard) end a special scheme's authority at "\" as well
        // as "/", so `https://evil.example\@bank.example/` opens evil.example: refuse it
        // rather than describe the code with a host the scanner never visits.
        if (str_contains($authority, '\\') && in_array($this->scheme, self::SPECIAL_SCHEMES, true)) {
            throw InvalidPayloadException::invalidFormat('Url', 'url');
        }

        $authority = substr($authority, (int) strrpos('@'.$authority, '@'));
        $this->host = str_starts_with($authority, '[')
            ? substr($authority, 0, (int) strpos($authority, ']') + 1)
            : explode(':', $authority)[0];

        if ($this->host === '' && in_array($this->scheme, ['http', 'https'], true)) {
            throw InvalidPayloadException::invalidFormat('Url', 'url');
        }
    }

    public function toQrString(): string
    {
        return $this->url;
    }

    public function requirements(): PayloadRequirements
    {
        return new PayloadRequirements(
            segmentation: Segmentation::Optimal,
            locked: SecretContent::isOtpauth($this->url) ? [PayloadRequirements::SENSITIVITY] : [],
        );
    }

    public function sensitivity(): Sensitivity
    {
        return SecretContent::isSecret($this->url) ? Sensitivity::Secret : Sensitivity::Public;
    }

    public function description(Translator $translator): string
    {
        $key = match (true) {
            SecretContent::isOtpauth($this->url) => 'otpauth',
            SecretContent::isWifi($this->url) => 'wifi',
            default => 'url',
        };

        return (string) $translator->get('qr::qr.descriptions.'.$key, ['host' => $this->host !== '' ? $this->host : $this->scheme]);
    }
}
