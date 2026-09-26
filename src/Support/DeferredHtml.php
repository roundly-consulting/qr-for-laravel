<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Support;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Stringable;

/**
 * Markup produced only when it is rendered.
 *
 * The Blade component returns this instead of a string: `Component::resolveView()` passes
 * an `Htmlable` through untouched but compiles a returned string (or a closure's string)
 * as a Blade template, which would execute `{{ }}`/`@php` inside a title and write one
 * compiled view per distinct QR code. Rendering lazily also lets the component see the
 * attribute bag, which Blade assigns after `resolveView()`.
 *
 * @internal
 */
final readonly class DeferredHtml implements Htmlable, Stringable
{
    /**
     * @param  Closure(): string  $render
     */
    public function __construct(private Closure $render) {}

    public function toHtml(): string
    {
        return ($this->render)();
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }
}
