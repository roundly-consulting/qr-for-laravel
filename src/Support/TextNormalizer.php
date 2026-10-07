<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Support;

use Normalizer;
use RoundlyConsulting\Qr\Exceptions\InvalidPayloadException;

/**
 * Payment text hygiene: CR, LF and TAB (the EPC and by square field separators) collapse to
 * one space, and — when ext-intl is available — text is NFC-normalised so a decomposed
 * "a + combining acute" counts and encodes as one "á".
 *
 * @internal
 */
final class TextNormalizer
{
    public static function clean(string $value): string
    {
        $value = (string) preg_replace('/[\r\n\t]+/', ' ', $value);

        if (class_exists(Normalizer::class) && mb_check_encoding($value, 'UTF-8')) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_C);

            if (is_string($normalized)) {
                return $normalized;
            }
        }

        return $value;
    }

    /**
     * Clean a payload's text field, refusing invalid UTF-8: it would pass the length checks
     * and be written raw, and then the payment decoders reject the code.
     *
     * @throws InvalidPayloadException
     */
    public static function field(string $value, string $payloadType, string $field): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            throw InvalidPayloadException::invalidFormat($payloadType, $field);
        }

        return self::clean($value);
    }
}
