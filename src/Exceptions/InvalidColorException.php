<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Exceptions;

/**
 * A colour is not on the SVG colour allow-list. The value is not echoed back: it may be a
 * hostile attribute-injection attempt.
 */
final class InvalidColorException extends InvalidOptionException
{
    public const string REASON_COLOR = 'color';

    public static function invalid(string $option = 'color'): self
    {
        return new self(
            sprintf('Invalid colour for [%s]: expected #rgb, #rgba, #rrggbb, #rrggbbaa, rgb()/rgba(), a CSS named colour, transparent or currentColor.', $option),
            self::REASON_COLOR,
            $option,
        );
    }
}
