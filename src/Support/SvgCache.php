<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Support;

use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use RoundlyConsulting\Crypto\Hash\Digest;
use RoundlyConsulting\Qr\ValueObjects\QrMatrix;
use RoundlyConsulting\Qr\ValueObjects\Svg;

/**
 * Optional Laravel-cache layer for rendered SVG of public payloads. Entries are stored as
 * plain JSON strings (no PHP object unserialisation) under
 * `<prefix>:<sha256(version ‖ fingerprint)>`; the fingerprint includes the resolved title
 * and description, so a translated default never leaks across locales.
 *
 * @internal
 */
final readonly class SvgCache
{
    /** Bump when the rendered output or the stored format changes. */
    public const string FORMAT_VERSION = 'qr-svg-2';

    public function __construct(private CacheFactory $cache) {}

    /**
     * @param  Closure(): Svg  $render
     * @param  Closure(): QrMatrix  $matrix  lazily re-encodes the matrix behind a cache hit
     */
    public function remember(string $fingerprint, Closure $render, Closure $matrix): Svg
    {
        if (! ConfigGuard::cacheEnabled()) {
            return $render();
        }

        $store = $this->cache->store(ConfigGuard::cacheStore());
        $key = ConfigGuard::cachePrefix().':'.(new Digest)->hex(self::FORMAT_VERSION."\0".$fingerprint);
        $cached = $store->get($key);

        if (is_string($cached) && ($svg = Svg::fromCachePayload($cached, $matrix)) !== null) {
            return $svg;
        }

        $svg = $render();
        $store->put($key, $svg->toCachePayload(), ConfigGuard::cacheTtl());

        return $svg;
    }
}
