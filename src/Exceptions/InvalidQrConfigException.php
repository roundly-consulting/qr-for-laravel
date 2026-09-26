<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Exceptions;

/**
 * A `config/qr.php` value is missing or out of shape. The message names the key, never
 * the configured value.
 */
final class InvalidQrConfigException extends QrException
{
    public const string REASON_INVALID_CONFIG = 'invalid_config';

    /**
     * The single-string constructor is what the toolkit's config validator calls when it
     * re-throws its own failure as this package's exception.
     */
    public function __construct(string $message = '', ?string $key = null)
    {
        parent::__construct($message, self::REASON_INVALID_CONFIG, $key);
    }

    public static function invalid(string $key, string $expectation): self
    {
        return new self(sprintf('Invalid QR configuration value for [%s]: %s.', $key, $expectation), $key);
    }
}
