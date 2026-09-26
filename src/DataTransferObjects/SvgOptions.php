<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\DataTransferObjects;

use RoundlyConsulting\Qr\Enums\FinderStyle;
use RoundlyConsulting\Qr\Enums\ModuleStyle;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;
use RoundlyConsulting\Qr\ValueObjects\Color;

/**
 * Fully resolved SVG rendering options.
 */
final readonly class SvgOptions
{
    public const int MAX_SIZE = 8192;

    public const int MAX_MARGIN = 64;

    /**
     * @throws InvalidOptionException
     */
    public function __construct(
        public ?int $size = 256,
        public int $margin = 4,
        public Color $foreground = new Color('#000000'),
        public Color $background = new Color('#ffffff'),
        public ModuleStyle $moduleStyle = ModuleStyle::Square,
        public float $moduleRadius = 0.5,
        public FinderStyle $finderStyle = FinderStyle::Square,
        public ?Color $finderColor = null,
        public bool $xmlDeclaration = false,
    ) {
        if ($size !== null && ($size < 1 || $size > self::MAX_SIZE)) {
            throw InvalidOptionException::size($size);
        }

        if ($margin < 0 || $margin > self::MAX_MARGIN) {
            throw InvalidOptionException::margin($margin);
        }

        if ($moduleRadius <= 0.0 || $moduleRadius > 0.5) {
            throw InvalidOptionException::radius($moduleRadius);
        }
    }
}
