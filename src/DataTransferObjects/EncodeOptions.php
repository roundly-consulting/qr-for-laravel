<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\DataTransferObjects;

use RoundlyConsulting\Qr\Enums\EciMode;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\Segmentation;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;

/**
 * Fully resolved encoder options.
 */
final readonly class EncodeOptions
{
    /**
     * @throws InvalidOptionException
     */
    public function __construct(
        public ErrorCorrection $errorCorrection = ErrorCorrection::Medium,
        public int $minVersion = 1,
        public int $maxVersion = 40,
        public ?int $mask = null,
        public bool $boostErrorCorrection = true,
        public EciMode $eci = EciMode::Auto,
        public Segmentation $segmentation = Segmentation::Optimal,
        public bool $kanji = false,
    ) {
        if ($minVersion < 1 || $maxVersion > 40 || $minVersion > $maxVersion) {
            throw InvalidOptionException::versionRange($minVersion, $maxVersion);
        }

        if ($mask !== null && ($mask < 0 || $mask > 7)) {
            throw InvalidOptionException::mask($mask);
        }
    }
}
