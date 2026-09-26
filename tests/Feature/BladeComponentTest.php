<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use RoundlyConsulting\Qr\Payloads\Url;
use RoundlyConsulting\Qr\QrServiceProvider;
use RoundlyConsulting\Qr\Support\DeferredHtml;
use RoundlyConsulting\Qr\View\Components\QrCode;

it('renders an inline SVG with forwarded attributes', function (): void {
    $html = Blade::render('<x-qr-code data="https://example.com" :size="120" margin="2" error-correction="H" foreground="#123456" background="#fafafa" title="Scan" description="Opens the site" class="mx-auto" data-test="qr" onclick="alert(1)" />');

    expect($html)->toStartWith('<svg ')
        ->and($html)->toContain('width="120"')
        ->and($html)->toContain('class="mx-auto" data-test="qr"')
        ->and($html)->not->toContain('onclick')
        ->and($html)->toContain('fill="#123456"')
        ->and($html)->toContain('<title>Scan</title><desc>Opens the site</desc>');
});

it('renders payload objects and img tags', function (): void {
    $html = Blade::render('<x-qr-code :data="$payload" as="img" title="Pay" class="w-40" />', ['payload' => new Url('https://example.com')]);

    expect($html)->toStartWith('<img src="data:image/svg+xml;charset=utf-8,')
        ->and($html)->toContain('alt="Pay"')
        ->and($html)->toContain('class="w-40"');
});

it('never compiles generated markup as Blade', function (): void {
    $template = '<x-qr-code :data="$data" :title="$title" />';
    $hostile = '{{ 7*7 }} @php echo "pwned"; @endphp <script>';

    $html = Blade::render($template, ['data' => 'first', 'title' => $hostile]);

    expect($html)->toContain('{{ 7*7 }} @php echo &quot;pwned&quot;; @endphp &lt;script&gt;')
        ->and($html)->not->toContain('49')
        ->and($html)->not->toContain('<script>');

    // A different code with a different title through the same template compiles nothing new.
    $views = storage_path('framework/views');
    $before = count(glob($views.'/*') ?: []);

    Blade::render($template, ['data' => 'second', 'title' => $hostile.' 2']);

    expect(count(glob($views.'/*') ?: []))->toBe($before);
});

it('returns a deferred Htmlable from render()', function (): void {
    $component = new QrCode('x');
    $method = new ReflectionMethod($component, 'render');

    expect((string) $method->getReturnType())->toBe('Illuminate\Contracts\Support\Htmlable')
        ->and($component->render())->toBeInstanceOf(DeferredHtml::class)
        ->and((string) $component->withAttributes([])->render())->toStartWith('<svg ');
});

it('rejects an unknown render mode', function (): void {
    Blade::render('<x-qr-code data="x" as="png" />');
})->throws(ViewException::class, 'Invalid render mode');

it('decodes Blade-escaped attribute values once', function (): void {
    $html = Blade::render('<x-qr-code data="x" :class="$c" aria-hidden />', ['c' => 'a & b']);

    expect($html)->toContain('class="a &amp; b"')
        ->and($html)->toContain('aria-hidden="aria-hidden"');
});

it('registers under the configured alias, or not at all', function (): void {
    expect(Blade::getClassComponentAliases()['qr-code'] ?? null)->toBe(QrCode::class);

    config(['qr.blade.component' => 'qr-tag']);
    bootQrProvider();

    expect(Blade::getClassComponentAliases()['qr-tag'] ?? null)->toBe(QrCode::class);

    $count = count(Blade::getClassComponentAliases());
    config(['qr.blade.component' => null]);
    bootQrProvider();

    expect(Blade::getClassComponentAliases())->toHaveCount($count);
});

function bootQrProvider(): void
{
    $provider = new QrServiceProvider(app());
    $provider->register();
    $provider->boot();
}
