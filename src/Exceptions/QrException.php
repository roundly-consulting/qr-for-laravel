<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base of every exception this package throws.
 *
 * Messages are fixed English developer messages so the framework-free core never needs a
 * translator. `$reason` is a stable snake-case key — also the `qr::qr.errors.<reason>`
 * translation key a host shows to end users — and `$field` names the offending field or
 * option. No message ever contains a payload value: 2FA seeds, IBANs, names and amounts
 * are secrets or personal data, and exception messages end up in logs.
 */
abstract class QrException extends RuntimeException
{
    public const string REASON_ERROR = 'error';

    public function __construct(
        string $message = '',
        public readonly string $reason = self::REASON_ERROR,
        public readonly ?string $field = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
