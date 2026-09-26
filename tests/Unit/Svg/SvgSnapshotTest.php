<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\DataTransferObjects\EncodeOptions;
use RoundlyConsulting\Qr\DataTransferObjects\SvgOptions;
use RoundlyConsulting\Qr\Encoder\Encoder;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\FinderStyle;
use RoundlyConsulting\Qr\Enums\ModuleStyle;
use RoundlyConsulting\Qr\Svg\SvgRenderer;
use RoundlyConsulting\Qr\ValueObjects\Color;

/**
 * @return array<string, SvgOptions>
 */
function snapshotStyles(): array
{
    return [
        'square' => new SvgOptions,
        'rounded' => new SvgOptions(moduleStyle: ModuleStyle::Rounded, moduleRadius: 0.35),
        'rounded-finders' => new SvgOptions(moduleStyle: ModuleStyle::Rounded, finderStyle: FinderStyle::Rounded),
        'dots' => new SvgOptions(moduleStyle: ModuleStyle::Dots, moduleRadius: 0.4, finderStyle: FinderStyle::Rounded),
        'finder-colour' => new SvgOptions(size: null, margin: 2, background: new Color('transparent'), finderColor: new Color('#0a58ca')),
    ];
}

it('matches the committed style snapshots', function (string $style): void {
    $matrix = (new Encoder)->encode('HELLO WORLD', new EncodeOptions(ErrorCorrection::Quartile, mask: 2, boostErrorCorrection: false));
    $svg = (new SvgRenderer)->render($matrix, snapshotStyles()[$style], 'QR code')->toString();

    expect($svg)->toBe(trim((string) file_get_contents(__DIR__."/../../Fixtures/svg/{$style}.svg")));
})->with(array_keys(snapshotStyles()));

it('pins the snapshot count', function (): void {
    expect(glob(__DIR__.'/../../Fixtures/svg/*.svg'))->toHaveCount(5);
});
