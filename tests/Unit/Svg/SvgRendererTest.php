<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\DataTransferObjects\EncodeOptions;
use RoundlyConsulting\Qr\DataTransferObjects\SvgOptions;
use RoundlyConsulting\Qr\Encoder\Encoder;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\FinderStyle;
use RoundlyConsulting\Qr\Enums\ModuleStyle;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;
use RoundlyConsulting\Qr\Svg\SvgRenderer;
use RoundlyConsulting\Qr\Tests\Support\GoldenFixtures;
use RoundlyConsulting\Qr\Tests\Support\PathRasterizer;
use RoundlyConsulting\Qr\ValueObjects\Color;
use RoundlyConsulting\Qr\ValueObjects\QrMatrix;

function helloMatrix(): QrMatrix
{
    return (new Encoder)->encode('HELLO WORLD', new EncodeOptions(ErrorCorrection::Quartile, mask: 2, boostErrorCorrection: false));
}

it('renders the documented square shape', function (): void {
    $svg = (new SvgRenderer)->render(helloMatrix(), new SvgOptions)->toString();

    expect($svg)->toStartWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 29 29" width="256" height="256" role="img" aria-label="QR code" shape-rendering="crispEdges"><title>QR code</title><rect width="29" height="29" fill="#ffffff"/><path fill="#000000" fill-rule="evenodd" d="M4 4h7v7h-7zM12 4h1v1h-1z')
        ->and($svg)->toContain('M5 5v5h5v-5z')
        ->and($svg)->toEndWith('"/></svg>')
        ->and(substr_count($svg, '<path'))->toBe(1)
        ->and($svg)->not->toContain(' id=');
});

it('traces a path that paints exactly the matrix for every golden fixture', function (): void {
    foreach (GoldenFixtures::all() as $case) {
        $matrix = (new Encoder)->encodeSegments($case['segments'], $case['options']);
        $svg = (new SvgRenderer)->render($matrix, new SvgOptions(margin: 3))->toString();

        expect(PathRasterizer::rows(PathRasterizer::pathData($svg), $matrix->size(), 3))->toBe($matrix->rows());
    }
});

it('paints every dark module in every style', function (ModuleStyle $modules, FinderStyle $finders, ?string $finderColor): void {
    $matrix = (new Encoder)->encode('styled modules 12345', new EncodeOptions(ErrorCorrection::High));
    $svg = (new SvgRenderer)->render($matrix, new SvgOptions(
        margin: 4,
        moduleStyle: $modules,
        moduleRadius: 0.4,
        finderStyle: $finders,
        finderColor: $finderColor === null ? null : new Color($finderColor),
    ))->toString();

    expect(PathRasterizer::rows(PathRasterizer::pathData($svg), $matrix->size(), 4))->toBe($matrix->rows())
        ->and(new DOMDocument()->loadXML($svg))->toBeTrue();
})->with([
    [ModuleStyle::Square, FinderStyle::Square, null],
    [ModuleStyle::Square, FinderStyle::Square, '#c00'],
    [ModuleStyle::Rounded, FinderStyle::Square, null],
    [ModuleStyle::Rounded, FinderStyle::Rounded, null],
    [ModuleStyle::Dots, FinderStyle::Square, null],
    [ModuleStyle::Dots, FinderStyle::Rounded, 'navy'],
]);

it('uses a second path only when the finder colour differs', function (): void {
    $renderer = new SvgRenderer;

    expect(substr_count($renderer->render(helloMatrix(), new SvgOptions(finderColor: new Color('#000')))->toString(), '<path'))->toBe(2)
        ->and(substr_count($renderer->render(helloMatrix(), new SvgOptions(finderColor: new Color('#000000')))->toString(), '<path'))->toBe(1)
        ->and($renderer->render(helloMatrix(), new SvgOptions(finderColor: new Color('red')))->toString())->toContain('<path fill="red"');
});

it('rounds convex corners only and drops crisp edges for curved styles', function (): void {
    $svg = (new SvgRenderer)->render(helloMatrix(), new SvgOptions(moduleStyle: ModuleStyle::Rounded, moduleRadius: 0.25))->toString();

    expect($svg)->toContain('a0.25 0.25 0 0 1')
        ->and($svg)->not->toContain('shape-rendering');
});

it('renders responsive, transparent and declared variants', function (): void {
    $svg = (new SvgRenderer)->render(helloMatrix(), new SvgOptions(size: null, margin: 0, background: new Color('transparent'), xmlDeclaration: true))->toString();

    expect($svg)->toStartWith('<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 21 21" role="img"')
        ->and($svg)->not->toContain('<rect')
        ->and($svg)->not->toContain('width="');
});

it('adds an escaped title and description', function (): void {
    $svg = (new SvgRenderer)->render(helloMatrix(), new SvgOptions, 'Pay <now> & "fast"', 'Scan it')->toString();

    expect($svg)->toContain('aria-label="Pay &lt;now&gt; &amp; &quot;fast&quot;"')
        ->and($svg)->toContain('<title>Pay &lt;now&gt; &amp; &quot;fast&quot;</title><desc>Scan it</desc>')
        ->and(new DOMDocument()->loadXML($svg))->toBeTrue();
});

it('is deterministic', function (): void {
    $renderer = new SvgRenderer;

    expect($renderer->render(helloMatrix(), new SvgOptions)->toString())->toBe($renderer->render(helloMatrix(), new SvgOptions)->toString());
});

it('carries matrix, sensitivity and geometry', function (): void {
    $svg = (new SvgRenderer)->render(helloMatrix(), new SvgOptions(size: 100, margin: 2), sensitivity: Sensitivity::Secret);

    expect($svg->matrix()->version())->toBe(1)
        ->and($svg->sensitivity())->toBe(Sensitivity::Secret)
        ->and($svg->width())->toBe(100)
        ->and($svg->viewBoxSize())->toBe(25);
});

it('validates rendering options', function (Closure $build): void {
    expect($build)->toThrow(InvalidOptionException::class);
})->with([
    fn () => new SvgOptions(size: 0),
    fn () => new SvgOptions(size: 8193),
    fn () => new SvgOptions(margin: -1),
    fn () => new SvgOptions(margin: 65),
    fn () => new SvgOptions(moduleRadius: 0.0),
    fn () => new SvgOptions(moduleRadius: 0.51),
]);
