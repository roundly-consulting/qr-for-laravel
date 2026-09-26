<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Exceptions;

use Throwable;

/**
 * An encoding or rendering option is out of range, or collides with a value a payload
 * locks. Not final: {@see InvalidColorException} extends it.
 */
class InvalidOptionException extends QrException
{
    public const string REASON_VERSION_RANGE = 'version_range';

    public const string REASON_MASK = 'mask';

    public const string REASON_SIZE = 'size';

    public const string REASON_MARGIN = 'margin';

    public const string REASON_RADIUS = 'radius';

    public const string REASON_ATTRIBUTE = 'attribute';

    public const string REASON_ERROR_CORRECTION = 'error_correction';

    public const string REASON_LOCKED_BY_PAYLOAD = 'locked_by_payload';

    public const string REASON_OUT_OF_BOUNDS = 'out_of_bounds';

    public const string REASON_RENDER_AS = 'render_as';

    public const string REASON_ECI = 'eci';

    /**
     * Final so the `new static` named constructors stay safe for subclasses.
     */
    final public function __construct(string $message = '', string $reason = self::REASON_ERROR, ?string $field = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $reason, $field, $previous);
    }

    public static function versionRange(int $min, int $max): static
    {
        return new static(sprintf('Invalid version range %d-%d: expected 1 <= min <= max <= 40.', $min, $max), self::REASON_VERSION_RANGE, 'version');
    }

    public static function mask(int $mask): static
    {
        return new static(sprintf('Invalid mask %d: expected 0-7 or null for automatic selection.', $mask), self::REASON_MASK, 'mask');
    }

    public static function size(int $size): static
    {
        return new static(sprintf('Invalid size %d: expected 1-8192 pixels or null for a responsive symbol.', $size), self::REASON_SIZE, 'size');
    }

    public static function margin(int $margin): static
    {
        return new static(sprintf('Invalid margin %d: expected 0-64 modules.', $margin), self::REASON_MARGIN, 'margin');
    }

    public static function radius(float $radius): static
    {
        return new static(sprintf('Invalid module radius %s: expected 0 < radius <= 0.5.', rtrim(rtrim(sprintf('%.4F', $radius), '0'), '.')), self::REASON_RADIUS, 'radius');
    }

    public static function attribute(): static
    {
        return new static('An SVG/img attribute name is not on the allow-list (class, style, id, data-*, aria-*).', self::REASON_ATTRIBUTE, 'attribute');
    }

    public static function errorCorrection(): static
    {
        return new static('Invalid error correction level: expected one of L, M, Q, H.', self::REASON_ERROR_CORRECTION, 'errorCorrection');
    }

    public static function lockedByPayload(string $option, string $payloadType): static
    {
        return new static(sprintf('The [%s] option is fixed by the %s payload specification and cannot be overridden.', $option, $payloadType), self::REASON_LOCKED_BY_PAYLOAD, $option);
    }

    public static function outOfBounds(int $x, int $y, int $size): static
    {
        return new static(sprintf('Module (%d, %d) is outside the %dx%d matrix.', $x, $y, $size, $size), self::REASON_OUT_OF_BOUNDS, 'module');
    }

    public static function eci(int $designator): static
    {
        return new static(sprintf('Invalid ECI designator %d: expected 0-999999.', $designator), self::REASON_ECI, 'eci');
    }

    public static function renderAs(): static
    {
        return new static('Invalid render mode: expected "svg" or "img".', self::REASON_RENDER_AS, 'as');
    }
}
