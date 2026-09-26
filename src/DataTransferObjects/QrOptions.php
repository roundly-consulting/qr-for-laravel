<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\DataTransferObjects;

use RoundlyConsulting\Qr\Enums\EciMode;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\FinderStyle;
use RoundlyConsulting\Qr\Enums\ModuleStyle;
use RoundlyConsulting\Qr\Enums\Sensitivity;

/**
 * Bulk overrides for one QR code. A null field means "not set": the payload's
 * requirements and then the configuration decide.
 */
final readonly class QrOptions
{
    public function __construct(
        public ?int $size = null,
        public ?int $margin = null,
        public ?ErrorCorrection $errorCorrection = null,
        public ?int $minVersion = null,
        public ?int $maxVersion = null,
        public ?int $mask = null,
        public ?string $foreground = null,
        public ?string $background = null,
        public ?ModuleStyle $moduleStyle = null,
        public ?FinderStyle $finderStyle = null,
        public ?string $title = null,
        public ?string $description = null,
        public ?EciMode $eci = null,
        public ?Sensitivity $sensitivity = null,
    ) {}
}
