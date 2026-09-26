<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Enums\FinderStyle;
use RoundlyConsulting\Qr\Svg\Path\FinderShapes;
use RoundlyConsulting\Qr\Svg\Path\OutlineTracer;
use RoundlyConsulting\Qr\Svg\Path\PathFormatter;
use RoundlyConsulting\Qr\Svg\Path\RoundedOutline;
use RoundlyConsulting\Qr\Svg\Path\SquarePath;
use RoundlyConsulting\Qr\Tests\Support\PathRasterizer;

it('formats numbers without locale or trailing zeros', function (float|int $value, string $expected): void {
    expect(PathFormatter::number($value))->toBe($expected);
})->with([
    [3, '3'],
    [0.5, '0.5'],
    [1.0, '1'],
    [-0.25, '-0.25'],
    [0.1234, '0.123'],
    [-0.0001, '0'],
    [0.0, '0'],
]);

it('formats axis lines and clockwise arcs', function (): void {
    expect(PathFormatter::line(1, 0, 2))->toBe('h2')
        ->and(PathFormatter::line(0, -1, 0.5))->toBe('v-0.5')
        ->and(PathFormatter::arc(0.5, 0.5, -0.5))->toBe('a0.5 0.5 0 0 1 0.5 -0.5');
});

it('traces a single module clockwise', function (): void {
    expect(OutlineTracer::loops(['1']))->toBe([[[0, 0], [1, 0], [1, 1], [0, 1]]])
        ->and(SquarePath::render(OutlineTracer::loops(['1']), 2))->toBe('M2 2h1v1h-1z');
});

it('keeps diagonally touching modules as separate loops', function (): void {
    $loops = OutlineTracer::loops(['10', '01']);

    expect($loops)->toHaveCount(2)
        ->and(PathRasterizer::rows(SquarePath::render($loops, 0), 2, 0))->toBe(['10', '01'])
        ->and(PathRasterizer::rows(SquarePath::render(OutlineTracer::loops(['01', '10']), 0), 2, 0))->toBe(['01', '10']);
});

it('traces holes counter-clockwise inside their outline', function (): void {
    $rows = ['111', '101', '111'];
    $path = SquarePath::render(OutlineTracer::loops($rows), 0);

    expect($path)->toBe('M0 0h3v3h-3zM1 1v1h1v-1z')
        ->and(PathRasterizer::rows($path, 3, 0))->toBe($rows);
});

it('honours an exclusion callback', function (): void {
    expect(OutlineTracer::loops(['11'], static fn (int $x): bool => $x === 1))->toBe([[[0, 0], [1, 0], [1, 1], [0, 1]]]);
});

it('rounds only convex corners', function (): void {
    // An L shape: five convex corners, one concave.
    $path = RoundedOutline::render(OutlineTracer::loops(['10', '11']), 0, 0.5);

    expect(substr_count($path, 'a0.5 0.5 0 0 1'))->toBe(5)
        ->and(PathRasterizer::rows($path, 2, 0))->toBe(['10', '11']);
});

it('draws finder shapes in both styles', function (FinderStyle $style): void {
    $rows = PathRasterizer::rows(FinderShapes::render(21, 0, $style), 21, 0);

    expect(substr($rows[0], 0, 7))->toBe('1111111')
        ->and(substr($rows[1], 0, 7))->toBe('1000001')
        ->and(substr($rows[3], 0, 7))->toBe('1011101')
        ->and(substr($rows[3], 14, 7))->toBe('1011101')
        ->and(substr($rows[17], 0, 7))->toBe('1011101')
        ->and($rows[10])->toBe(str_repeat('0', 21));
})->with(FinderStyle::cases());

it('recognises finder regions', function (): void {
    expect(FinderShapes::isFinder(6, 6, 21))->toBeTrue()
        ->and(FinderShapes::isFinder(14, 0, 21))->toBeTrue()
        ->and(FinderShapes::isFinder(0, 14, 21))->toBeTrue()
        ->and(FinderShapes::isFinder(7, 0, 21))->toBeFalse()
        ->and(FinderShapes::isFinder(14, 14, 21))->toBeFalse();
});
