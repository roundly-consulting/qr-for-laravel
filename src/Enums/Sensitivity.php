<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Enums;

use RoundlyConsulting\Enums\Helpers;

/**
 * How much the encoded content may be shared: drives the in-process memo, the
 * rendered-SVG cache and HTTP caching headers.
 */
enum Sensitivity: string
{
    use Helpers;

    /** Anything a stranger may see (URLs, public text): memoised, cacheable, `public`. */
    case Public = 'public';

    /** Personal data (payments, contact details): never shared-cached, served `private`. */
    case Personal = 'personal';

    /** Secrets (2FA seeds, Wi-Fi passwords): never memoised or cached, served `no-store`. */
    case Secret = 'secret';

    public function isCacheable(): bool
    {
        return $this === self::Public;
    }

    public function cacheControl(int $maxAge, bool $immutable = false): string
    {
        return match ($this) {
            self::Public => sprintf('public, max-age=%d', $maxAge).($immutable ? ', immutable' : ''),
            self::Personal => sprintf('private, max-age=%d', $maxAge),
            self::Secret => 'no-store, max-age=0',
        };
    }
}
