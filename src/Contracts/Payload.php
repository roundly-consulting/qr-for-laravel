<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Contracts;

use Illuminate\Contracts\Translation\Translator;
use RoundlyConsulting\Qr\DataTransferObjects\PayloadRequirements;
use RoundlyConsulting\Qr\Enums\Sensitivity;

/**
 * Typed QR content. Implement it for your own formats (tickets, deep links, …).
 */
interface Payload
{
    /**
     * The exact bytes encoded into the symbol (UTF-8 unless the payload says otherwise).
     */
    public function toQrString(): string;

    /**
     * Encoding defaults, caps and spec-locked options this payload type imposes.
     */
    public function requirements(): PayloadRequirements;

    /**
     * Drives the in-process memo, the rendered-SVG cache and HTTP caching.
     */
    public function sensitivity(): Sensitivity;

    /**
     * Default accessible description (`<desc>`), or null for none.
     */
    public function description(Translator $translator): ?string;
}
