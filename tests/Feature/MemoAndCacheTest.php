<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Facades\Qr;
use RoundlyConsulting\Qr\Support\MatrixMemo;
use RoundlyConsulting\Qr\Support\SvgCache;
use RoundlyConsulting\Qr\ValueObjects\Svg;

beforeEach(function (): void {
    config(['cache.default' => 'array']);
});

it('memoises public matrices only', function (): void {
    $memo = app(MatrixMemo::class);
    $memo->flush();

    Qr::text('public')->matrix();
    Qr::text('public')->matrix();
    Qr::email('a@b.co')->matrix();
    Qr::otpauth('otpauth://totp/A:b?secret=JBSWY3DP')->matrix();
    Qr::text('public')->sensitivity(Sensitivity::Personal)->matrix();

    expect($memo->count())->toBe(1);
});

it('evicts the least recently used entry', function (): void {
    $memo = new MatrixMemo(2);
    $encode = fn () => Qr::text('m')->sensitivity(Sensitivity::Secret)->matrix();

    $memo->remember('a', $encode);
    $memo->remember('b', $encode);
    $memo->remember('a', $encode);
    $memo->remember('c', $encode);

    expect($memo->has('a'))->toBeTrue()
        ->and($memo->has('b'))->toBeFalse()
        ->and($memo->has('c'))->toBeTrue()
        ->and($memo->count())->toBe(2);
});

it('does not memoise when disabled', function (): void {
    $memo = new MatrixMemo(0);
    $memo->remember('a', fn () => Qr::text('m')->matrix());

    expect($memo->count())->toBe(0);
});

it('caches rendered SVG for public payloads when enabled', function (): void {
    config(['qr.cache.enabled' => true, 'qr.cache.store' => 'array']);

    $first = Qr::text('cached')->svg();
    $keys = array_keys(cacheEntries());

    expect($keys)->toHaveCount(1)
        ->and($keys[0])->toStartWith('qr:');

    $second = Qr::text('cached')->svg();

    expect($second->toString())->toBe($first->toString())
        ->and($second->matrix()->rows())->toBe($first->matrix()->rows())
        ->and(cacheEntries())->toHaveCount(1);
});

it('keeps the XML declaration on a cache hit', function (bool $viaConfig): void {
    config(['qr.cache.enabled' => true, 'qr.cache.store' => 'array', 'qr.svg.xml_declaration' => $viaConfig]);
    $pending = $viaConfig ? Qr::text('declared') : Qr::text('declared')->xmlDeclaration();

    $miss = $pending->svg()->toString();
    $hit = $pending->svg()->toString();

    expect($miss)->toStartWith(Svg::XML_DECLARATION)
        ->and($hit)->toBe($miss)
        ->and(cacheEntries())->toHaveCount(1);
})->with(['config' => [true], 'builder' => [false]]);

it('never writes secret or personal payloads to the cache store', function (): void {
    config(['qr.cache.enabled' => true]);

    Qr::otpauth('otpauth://totp/A:b?secret=JBSWY3DP')->svg();
    Qr::wifi('Net', 'password123')->svg();
    Qr::email('a@b.co')->svg();

    expect(cacheEntries())->toBe([]);
});

it('keys cache entries by the resolved, translated title', function (): void {
    config(['qr.cache.enabled' => true]);
    app('translator')->addLines(['qr.title' => 'QR-Code'], 'de', 'qr');

    $english = Qr::text('same')->svg()->toString();
    app()->setLocale('de');
    $german = Qr::text('same')->svg()->toString();

    expect($english)->toContain('<title>QR code</title>')
        ->and($german)->toContain('<title>QR-Code</title>')
        ->and(cacheEntries())->toHaveCount(2);
});

it('does not touch the store when the cache is disabled', function (): void {
    Qr::text('uncached')->svg();

    expect(cacheEntries())->toBe([]);
});

it('re-renders an unreadable cache entry', function (string $payload): void {
    config(['qr.cache.enabled' => true]);
    Qr::text('broken')->svg();
    $key = array_key_first(cacheEntries());
    Cache::store('array')->put((string) $key, $payload, 60);

    expect(Qr::text('broken')->svg()->toString())->toStartWith('<svg ')
        ->and(Cache::store('array')->get((string) $key))->not->toBe($payload);
})->with([
    'not json' => ['{'],
    'wrong shape' => ['{"attributes":[],"content":"x"}'],
    'bad attribute' => ['{"attributes":{"a":1},"content":"x","width":null,"viewBox":1}'],
    'bad width' => ['{"attributes":{},"content":"x","width":"1","viewBox":1}'],
    'no declaration flag' => ['{"attributes":{},"content":"x","width":null,"viewBox":1}'],
]);

it('restores a cached SVG with a lazily re-encoded matrix', function (): void {
    $restored = Svg::fromCachePayload(Qr::text('lazy')->svg()->toCachePayload(), fn () => Qr::text('lazy')->matrix());

    expect($restored)->not->toBeNull()
        ->and($restored?->matrix()->rows())->toBe(Qr::text('lazy')->matrix()->rows())
        ->and($restored?->sensitivity())->toBe(Sensitivity::Public)
        ->and(SvgCache::FORMAT_VERSION)->toBe('qr-svg-2');
});

/**
 * @return array<string, mixed>
 */
function cacheEntries(): array
{
    $store = Cache::store('array')->getStore();
    $storage = (new ReflectionProperty($store, 'storage'))->getValue($store);

    return is_array($storage) ? $storage : [];
}
