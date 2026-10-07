<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Svg;

use RoundlyConsulting\Qr\DataTransferObjects\SvgOptions;
use RoundlyConsulting\Qr\Enums\FinderStyle;
use RoundlyConsulting\Qr\Enums\ModuleStyle;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Svg\Path\DotPath;
use RoundlyConsulting\Qr\Svg\Path\FinderShapes;
use RoundlyConsulting\Qr\Svg\Path\OutlineTracer;
use RoundlyConsulting\Qr\Svg\Path\RoundedOutline;
use RoundlyConsulting\Qr\Svg\Path\SquarePath;
use RoundlyConsulting\Qr\ValueObjects\QrMatrix;
use RoundlyConsulting\Qr\ValueObjects\Svg;

/**
 * Renders a matrix as one optimised SVG document: coordinates in module units, an exact
 * viewBox (symbol + quiet zone), an optional background rectangle and a single even-odd
 * path for the dark modules (a second path only when the finders get their own colour).
 * Text is escaped, colours come from the allow-list, numbers from the path formatter.
 */
final class SvgRenderer
{
    public function render(QrMatrix $matrix, SvgOptions $options, ?string $title = null, ?string $description = null, Sensitivity $sensitivity = Sensitivity::Public): Svg
    {
        $size = $matrix->size();
        $viewBox = $size + 2 * $options->margin;
        $title ??= 'QR code';

        $attributes = [
            'xmlns' => 'http://www.w3.org/2000/svg',
            'viewBox' => '0 0 '.$viewBox.' '.$viewBox,
        ];

        if ($options->size !== null) {
            $attributes['width'] = (string) $options->size;
            $attributes['height'] = (string) $options->size;
        }

        $attributes['role'] = 'img';
        $attributes['aria-label'] = Svg::escape($title);

        if ($options->moduleStyle === ModuleStyle::Square) {
            $attributes['shape-rendering'] = 'crispEdges';
        }

        $content = '<title>'.Svg::escape($title).'</title>';

        if ($description !== null && $description !== '') {
            $content .= '<desc>'.Svg::escape($description).'</desc>';
        }

        if (! $options->background->isTransparent()) {
            $content .= '<rect width="'.$viewBox.'" height="'.$viewBox.'" fill="'.$options->background->toSvg().'"/>';
        }

        $content .= $this->paths($matrix, $options);

        return new Svg($attributes, $content, $matrix, $sensitivity, $options->size, $viewBox, $options->xmlDeclaration);
    }

    private function paths(QrMatrix $matrix, SvgOptions $options): string
    {
        $size = $matrix->size();
        $finderColor = $options->finderColor ?? $options->foreground;
        $separateColor = ! $finderColor->equals($options->foreground);
        // Finders are drawn on their own whenever their shape could differ from the traced
        // modules, so FinderStyle::Square is always square and a colour never changes geometry.
        $separateFinders = $separateColor || $options->finderStyle !== FinderStyle::Square || $options->moduleStyle !== ModuleStyle::Square;

        $exclude = $separateFinders
            ? static fn (int $x, int $y): bool => FinderShapes::isFinder($x, $y, $size)
            : null;

        $modules = match ($options->moduleStyle) {
            ModuleStyle::Square => SquarePath::render(OutlineTracer::loops($matrix->rows(), $exclude), $options->margin),
            ModuleStyle::Rounded => RoundedOutline::render(OutlineTracer::loops($matrix->rows(), $exclude), $options->margin, $options->moduleRadius),
            ModuleStyle::Dots => DotPath::render($matrix->rows(), $options->margin, $options->moduleRadius, $exclude ?? static fn (): bool => false),
        };

        $finders = $separateFinders ? FinderShapes::render($size, $options->margin, $options->finderStyle) : '';

        if (! $separateColor) {
            return self::path($options->foreground->toSvg(), $modules.$finders);
        }

        return self::path($options->foreground->toSvg(), $modules).self::path($finderColor->toSvg(), $finders);
    }

    private static function path(string $fill, string $data): string
    {
        return '<path fill="'.$fill.'" fill-rule="evenodd" d="'.$data.'"/>';
    }
}
