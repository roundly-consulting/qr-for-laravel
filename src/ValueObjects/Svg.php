<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\ValueObjects;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use RoundlyConsulting\Crypto\Codec\Base64;
use RoundlyConsulting\Crypto\Hash\Digest;
use RoundlyConsulting\Qr\Enums\DataUriEncoding;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;
use RoundlyConsulting\Qr\Support\ConfigGuard;
use Stringable;

/**
 * A rendered QR code as SVG markup. Immutable; deterministic for the same input (no ids,
 * no timestamps), so the ETag is stable.
 */
final class Svg implements Htmlable, Responsable, Stringable
{
    public const string XML_DECLARATION = '<?xml version="1.0" encoding="UTF-8"?>';

    public const string CONTENT_TYPE = 'image/svg+xml; charset=utf-8';

    public const string CONTENT_SECURITY_POLICY = "default-src 'none'; style-src 'unsafe-inline'";

    private const string ATTRIBUTE_PATTERN = '/^(class|style|id|data-[a-z0-9-]+|aria-[a-z]+)$/';

    /**
     * @param  array<string, string>  $rootAttributes  already escaped, in output order
     * @param  QrMatrix|(Closure(): QrMatrix)  $matrix  a closure defers encoding (cache hits)
     */
    public function __construct(
        private readonly array $rootAttributes,
        private readonly string $content,
        private QrMatrix|Closure $matrix,
        private readonly Sensitivity $sensitivity,
        private readonly ?int $width,
        private readonly int $viewBoxSize,
        private readonly bool $xmlDeclaration = false,
    ) {}

    public function toString(): string
    {
        $attributes = '';

        foreach ($this->rootAttributes as $name => $value) {
            $attributes .= ' '.$name.'="'.$value.'"';
        }

        return ($this->xmlDeclaration ? self::XML_DECLARATION : '').'<svg'.$attributes.'>'.$this->content.'</svg>';
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toHtml(): string
    {
        return $this->withXmlDeclaration(false)->toString();
    }

    public function toDataUri(DataUriEncoding $encoding = DataUriEncoding::Percent): string
    {
        $markup = $this->toString();

        return match ($encoding) {
            DataUriEncoding::Percent => 'data:image/svg+xml;charset=utf-8,'.rawurlencode($markup),
            DataUriEncoding::Base64 => 'data:image/svg+xml;base64,'.Base64::encode($markup),
        };
    }

    /**
     * An `<img>` tag carrying the SVG as a data URI. Extra attributes are allow-listed
     * (class, style, id, data-*, aria-*) and escaped.
     *
     * @param  array<string, string|int|float|bool>  $attributes
     *
     * @throws InvalidOptionException
     */
    public function toImgTag(?string $alt = null, array $attributes = []): HtmlString
    {
        $tag = '<img src="'.self::escape($this->toDataUri()).'" alt="'.self::escape($alt ?? $this->label()).'"';

        if ($this->width !== null) {
            $tag .= ' width="'.$this->width.'" height="'.$this->width.'"';
        }

        foreach (self::allowListed($attributes) as $name => $value) {
            $tag .= ' '.$name.'="'.$value.'"';
        }

        return new HtmlString($tag.'>');
    }

    /**
     * Extra attributes on the root `<svg>` element, allow-listed and escaped. A key that is
     * already present (e.g. `aria-label`) is replaced.
     *
     * @param  array<string, string|int|float|bool>  $attributes
     *
     * @throws InvalidOptionException
     */
    public function withAttributes(array $attributes): self
    {
        return new self(
            [...$this->rootAttributes, ...self::allowListed($attributes)],
            $this->content,
            $this->matrix,
            $this->sensitivity,
            $this->width,
            $this->viewBoxSize,
            $this->xmlDeclaration,
        );
    }

    public function withXmlDeclaration(bool $include = true): self
    {
        return new self($this->rootAttributes, $this->content, $this->matrix, $this->sensitivity, $this->width, $this->viewBoxSize, $include);
    }

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        return $this->respond($request, 'inline', 'qr-code.svg');
    }

    public function download(string $filename = 'qr-code.svg', ?Request $request = null): Response
    {
        return $this->respond($request ?? new Request, 'attachment', $filename);
    }

    public function matrix(): QrMatrix
    {
        if ($this->matrix instanceof Closure) {
            $this->matrix = ($this->matrix)();
        }

        return $this->matrix;
    }

    public function sensitivity(): Sensitivity
    {
        return $this->sensitivity;
    }

    /**
     * A strong ETag over the markup: the first 32 hex characters of its SHA-256.
     */
    public function etag(): string
    {
        return '"'.substr((new Digest)->hex($this->withXmlDeclaration(false)->toString()), 0, 32).'"';
    }

    public function width(): ?int
    {
        return $this->width;
    }

    public function viewBoxSize(): int
    {
        return $this->viewBoxSize;
    }

    /**
     * The cache representation: plain JSON of the markup parts, restorable without
     * unserialising objects. The matrix is not stored; a restored SVG re-encodes it only
     * if {@see self::matrix()} is called.
     *
     * @internal
     */
    public function toCachePayload(): string
    {
        return (string) json_encode([
            'attributes' => $this->rootAttributes,
            'content' => $this->content,
            'width' => $this->width,
            'viewBox' => $this->viewBoxSize,
            'xmlDeclaration' => $this->xmlDeclaration,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Restore a cached public SVG, or null when the entry is unreadable (it is then
     * re-rendered).
     *
     * @param  Closure(): QrMatrix  $matrix
     *
     * @internal
     */
    public static function fromCachePayload(string $payload, Closure $matrix): ?self
    {
        $data = json_decode($payload, true);

        if (! is_array($data) || ! is_array($data['attributes'] ?? null) || ! is_string($data['content'] ?? null)
            || ! is_int($data['viewBox'] ?? null) || ! array_key_exists('width', $data) || ! (is_int($data['width']) || $data['width'] === null)
            || ! is_bool($data['xmlDeclaration'] ?? null)) {
            return null;
        }

        $attributes = [];

        foreach ($data['attributes'] as $name => $value) {
            if (! is_string($name) || ! is_string($value)) {
                return null;
            }

            $attributes[$name] = $value;
        }

        return new self($attributes, $data['content'], $matrix, Sensitivity::Public, $data['width'], $data['viewBox'], $data['xmlDeclaration']);
    }

    /**
     * Escape text for an XML text node or attribute. Characters XML 1.0 forbids — C0
     * controls other than TAB/LF/CR and the noncharacters U+FFFE/U+FFFF — are dropped (they
     * cannot even be written as character references) and invalid UTF-8 is replaced, so the
     * document always stays well-formed.
     */
    public static function escape(string $value): string
    {
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');

        // ENT_SUBSTITUTE leaves valid UTF-8, so the /u pattern always applies.
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x{FFFE}\x{FFFF}]/u', '', $escaped);
    }

    private function label(): string
    {
        return html_entity_decode($this->rootAttributes['aria-label'] ?? 'QR code', ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function respond(Request $request, string $disposition, string $filename): Response
    {
        $response = new Response($this->withXmlDeclaration()->toString(), 200, [
            'Content-Type' => self::CONTENT_TYPE,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => self::CONTENT_SECURITY_POLICY,
        ]);

        $filename = str_replace(['/', '\\', '"'], '_', $filename);
        $fallback = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', Str::ascii($filename));
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition($disposition, $filename, $fallback));
        $response->headers->set('Cache-Control', $this->sensitivity->cacheControl(ConfigGuard::responseMaxAge(), ConfigGuard::responseImmutable()));

        if ($this->sensitivity === Sensitivity::Secret) {
            $response->headers->set('Pragma', 'no-cache');

            return $response;
        }

        $response->setEtag(trim($this->etag(), '"'));
        $response->isNotModified($request);

        return $response;
    }

    /**
     * @param  array<string, string|int|float|bool>  $attributes
     * @return array<string, string>
     *
     * @throws InvalidOptionException
     */
    private static function allowListed(array $attributes): array
    {
        $clean = [];

        foreach ($attributes as $name => $value) {
            if (preg_match(self::ATTRIBUTE_PATTERN, $name) !== 1) {
                throw InvalidOptionException::attribute();
            }

            $clean[$name] = self::escape(is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
        }

        return $clean;
    }
}
