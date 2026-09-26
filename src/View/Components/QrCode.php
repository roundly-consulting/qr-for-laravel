<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\View\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\View\Component;
use RoundlyConsulting\Qr\Contracts\Payload;
use RoundlyConsulting\Qr\Contracts\QrFactory;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;
use RoundlyConsulting\Qr\Support\DeferredHtml;

/**
 * `<x-qr-code :data="$payload" :size="200" title="Scan to pay" class="mx-auto" />`.
 *
 * Security: render() returns an Htmlable, never a string. Component::resolveView()
 * compiles a returned string as a Blade template, which would execute `{{ }}` or `@php`
 * smuggled in through a title and write one compiled view per distinct code. Only
 * allow-listed attributes (class, style, id, data-*, aria-*) reach the markup.
 */
final class QrCode extends Component
{
    private const string ATTRIBUTE_PATTERN = '/^(class|style|id|data-[a-z0-9-]+|aria-[a-z]+)$/';

    public function __construct(
        public string|Payload $data,
        public ?int $size = null,
        public ?int $margin = null,
        public ?string $errorCorrection = null,
        public ?string $foreground = null,
        public ?string $background = null,
        public ?string $title = null,
        public ?string $description = null,
        public string $as = 'svg',
    ) {}

    public function render(): Htmlable
    {
        return new DeferredHtml(fn (): string => $this->markup());
    }

    private function markup(): string
    {
        if (! in_array($this->as, ['svg', 'img'], true)) {
            throw InvalidOptionException::renderAs();
        }

        $pending = app(QrFactory::class)->make($this->data);

        if ($this->size !== null) {
            $pending = $pending->size($this->size);
        }

        if ($this->margin !== null) {
            $pending = $pending->margin($this->margin);
        }

        if ($this->errorCorrection !== null) {
            $pending = $pending->errorCorrection($this->errorCorrection);
        }

        if ($this->foreground !== null) {
            $pending = $pending->foreground($this->foreground);
        }

        if ($this->background !== null) {
            $pending = $pending->background($this->background);
        }

        if ($this->title !== null) {
            $pending = $pending->title($this->title);
        }

        if ($this->description !== null) {
            $pending = $pending->description($this->description);
        }

        $svg = $pending->svg();
        $attributes = $this->forwardedAttributes();

        return $this->as === 'img'
            ? $svg->toImgTag($this->title, $attributes)->toHtml()
            : $svg->withAttributes($attributes)->toHtml();
    }

    /**
     * Allow-listed attributes from the bag. Blade has already HTML-escaped interpolated
     * values; they are decoded here once so the SVG escaping does not double them.
     *
     * @return array<string, string>
     */
    private function forwardedAttributes(): array
    {
        $forwarded = [];

        foreach ($this->attributes->getAttributes() as $name => $value) {
            if (is_string($name) && preg_match(self::ATTRIBUTE_PATTERN, $name) === 1 && is_scalar($value) && $value !== false) {
                $forwarded[$name] = $value === true ? $name : html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $forwarded;
    }
}
