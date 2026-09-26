<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\DataTransferObjects;

use RoundlyConsulting\Qr\Enums\EciMode;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\Segmentation;

/**
 * What a payload type imposes on encoding. Unset fields fall through to configuration.
 * `maxVersion` is a hard cap; options named in `locked` are spec-mandated and may not be
 * overridden with a different value.
 */
final readonly class PayloadRequirements
{
    public const string ERROR_CORRECTION = 'errorCorrection';

    public const string ECI = 'eci';

    public const string SEGMENTATION = 'segmentation';

    public const string BOOST_ERROR_CORRECTION = 'boostErrorCorrection';

    public const string SENSITIVITY = 'sensitivity';

    /**
     * @param  list<string>  $locked  option names from the constants above
     */
    public function __construct(
        public ?ErrorCorrection $errorCorrection = null,
        public ?int $maxVersion = null,
        public ?Segmentation $segmentation = null,
        public ?EciMode $eci = null,
        public ?bool $boostErrorCorrection = null,
        public array $locked = [],
    ) {}

    public function locks(string $option): bool
    {
        return in_array($option, $this->locked, true);
    }
}
