<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\DataTransferObjects\SvgOptions;
use RoundlyConsulting\Qr\Encoder\Encoder;
use RoundlyConsulting\Qr\Exceptions\InvalidColorException;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;
use RoundlyConsulting\Qr\Facades\Qr;
use RoundlyConsulting\Qr\Svg\SvgRenderer;
use RoundlyConsulting\Qr\ValueObjects\Color;
use RoundlyConsulting\Qr\ValueObjects\Svg;

it('never emits active content whatever the title, description or attribute values', function (string $hostile): void {
    $svg = (new SvgRenderer)->render((new Encoder)->encode($hostile), new SvgOptions, $hostile, $hostile)
        ->withAttributes(['class' => $hostile, 'data-x' => $hostile, 'style' => $hostile])
        ->toString();

    $document = new DOMDocument;

    expect($document->loadXML($svg))->toBeTrue()
        ->and($document->getElementsByTagName('script')->length)->toBe(0)
        ->and($document->getElementsByTagName('foreignObject')->length)->toBe(0);

    foreach ($document->getElementsByTagName('*') as $element) {
        foreach ($element->attributes ?? [] as $attribute) {
            expect(str_starts_with(strtolower($attribute->name), 'on'))->toBeFalse()
                ->and(in_array($attribute->name, ['href', 'xlink:href'], true))->toBeFalse();
        }
    }
})->with([
    '<script>alert(1)</script>',
    '"><svg onload=alert(1)>',
    "' onmouseover='x",
    ']]><foreignObject/>',
    '{{ 7*7 }} @php echo 1; @endphp',
    "\x00\x1F invalid \xFF utf-8",
    '<a xlink:href="javascript:alert(1)">x</a>',
    "noncharacters \u{FFFE}\u{FFFF} end",
]);

it('drops the XML 1.0 noncharacters U+FFFE and U+FFFF', function (): void {
    expect(Svg::escape("a\u{FFFE}b\u{FFFF}c\u{FFFD}"))->toBe("abc\u{FFFD}");

    $svg = Qr::url("https://exa\u{FFFF}mple.com")->svg()->toString();

    expect((new DOMDocument)->loadXML($svg))->toBeTrue();
});

it('refuses hostile colours before they reach the markup', function (string $colour): void {
    new SvgOptions(foreground: Color::parse($colour));
})->throws(InvalidColorException::class)->with([
    'red" onload="alert(1)',
    'url(javascript:alert(1))',
    'var(--x)',
    'expression(alert(1))',
    '#000;fill:url(#a)',
]);

it('refuses hostile attribute names', function (): void {
    (new SvgRenderer)->render((new Encoder)->encode('x'), new SvgOptions)->withAttributes(['onclick' => 'x']);
})->throws(InvalidOptionException::class);
