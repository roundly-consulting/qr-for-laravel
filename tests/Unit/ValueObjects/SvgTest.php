<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RoundlyConsulting\Crypto\Codec\Base64;
use RoundlyConsulting\Qr\DataTransferObjects\SvgOptions;
use RoundlyConsulting\Qr\Encoder\Encoder;
use RoundlyConsulting\Qr\Enums\DataUriEncoding;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;
use RoundlyConsulting\Qr\Svg\SvgRenderer;
use RoundlyConsulting\Qr\ValueObjects\Svg;

function sampleSvg(Sensitivity $sensitivity = Sensitivity::Public, ?int $size = 200): Svg
{
    return (new SvgRenderer)->render((new Encoder)->encode('https://example.com'), new SvgOptions(size: $size), 'Scan me', null, $sensitivity);
}

it('converts to strings and html without an XML declaration', function (): void {
    $svg = sampleSvg();

    expect((string) $svg)->toBe($svg->toString())
        ->and($svg->toHtml())->toStartWith('<svg ')
        ->and($svg->withXmlDeclaration()->toString())->toStartWith(Svg::XML_DECLARATION.'<svg ')
        ->and($svg->withXmlDeclaration()->toHtml())->toStartWith('<svg ')
        ->and($svg->withXmlDeclaration()->withXmlDeclaration(false)->toString())->toBe($svg->toString());
});

it('builds percent and base64 data URIs', function (): void {
    $svg = sampleSvg();
    $percent = $svg->toDataUri();
    $base64 = $svg->toDataUri(DataUriEncoding::Base64);

    expect($percent)->toStartWith('data:image/svg+xml;charset=utf-8,%3Csvg%20')
        ->and(rawurldecode(substr($percent, strlen('data:image/svg+xml;charset=utf-8,'))))->toBe($svg->toString())
        ->and($base64)->toStartWith('data:image/svg+xml;base64,PHN2Zy')
        ->and(Base64::decode(substr($base64, strlen('data:image/svg+xml;base64,'))))->toBe($svg->toString());
});

it('renders an img tag with allow-listed attributes', function (): void {
    $tag = sampleSvg()->toImgTag(attributes: ['class' => 'qr "x"', 'data-id' => 5, 'aria-hidden' => true])->toHtml();

    expect($tag)->toStartWith('<img src="data:image/svg+xml;charset=utf-8,%3Csvg')
        ->and($tag)->toContain('alt="Scan me" width="200" height="200" class="qr &quot;x&quot;" data-id="5" aria-hidden="true">')
        ->and(sampleSvg(size: null)->toImgTag('Pay')->toHtml())->toContain('alt="Pay">');
});

it('adds, replaces and escapes root attributes', function (): void {
    $svg = sampleSvg()->withAttributes(['class' => 'w-40', 'aria-label' => 'Custom <label>', 'data-qr' => 'yes']);

    expect($svg->toString())->toContain('aria-label="Custom &lt;label&gt;"')
        ->and($svg->toString())->toContain(' class="w-40" data-qr="yes">')
        ->and(substr_count($svg->toString(), 'aria-label'))->toBe(1)
        ->and(new DOMDocument()->loadXML($svg->toString()))->toBeTrue();
});

it('rejects attributes off the allow-list', function (string $name): void {
    sampleSvg()->withAttributes([$name => 'x']);
})->throws(InvalidOptionException::class)->with(['onload', 'href', 'xlink:href', 'style onload', 'data-X', 'aria-1', '']);

it('rejects img attributes off the allow-list', function (): void {
    sampleSvg()->toImgTag(attributes: ['onerror' => 'alert(1)']);
})->throws(InvalidOptionException::class);

it('computes a stable strong ETag', function (): void {
    expect(sampleSvg()->etag())->toMatch('/^"[0-9a-f]{32}"$/')
        ->and(sampleSvg()->etag())->toBe(sampleSvg()->withXmlDeclaration()->etag());
});

it('serves public codes cacheably with an ETag and 304', function (): void {
    $svg = sampleSvg();
    $response = $svg->toResponse(new Request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toStartWith(Svg::XML_DECLARATION)
        ->and($response->headers->get('Content-Type'))->toBe('image/svg+xml; charset=utf-8')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Content-Security-Policy'))->toBe("default-src 'none'; style-src 'unsafe-inline'")
        ->and($response->headers->get('Content-Disposition'))->toBe('inline; filename=qr-code.svg')
        ->and($response->headers->get('Cache-Control'))->toContain('public')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=86400')
        ->and($response->headers->get('ETag'))->toBe($svg->etag());

    $request = new Request;
    $request->headers->set('If-None-Match', $svg->etag());

    expect($svg->toResponse($request)->getStatusCode())->toBe(304);
});

it('adds immutable when configured', function (): void {
    config(['qr.response.immutable' => true, 'qr.response.max_age' => 60]);

    expect(sampleSvg()->toResponse(new Request)->headers->get('Cache-Control'))->toContain('immutable')->toContain('max-age=60');
});

it('serves personal codes privately', function (): void {
    $cache = sampleSvg(Sensitivity::Personal)->toResponse(new Request)->headers->get('Cache-Control');

    expect($cache)->toContain('private')->not->toContain('public');
});

it('serves secrets with no-store and no ETag', function (): void {
    $response = sampleSvg(Sensitivity::Secret)->toResponse(new Request);

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Pragma'))->toBe('no-cache')
        ->and($response->headers->has('ETag'))->toBeFalse();
});

it('downloads with a safe attachment filename', function (): void {
    expect(sampleSvg()->download()->headers->get('Content-Disposition'))->toBe('attachment; filename=qr-code.svg')
        ->and(sampleSvg()->download('../faktúra "1".svg')->headers->get('Content-Disposition'))
        ->toBe('attachment; filename=.._faktura__1_.svg; filename*=utf-8\'\'.._fakt%C3%BAra%20_1_.svg');
});
