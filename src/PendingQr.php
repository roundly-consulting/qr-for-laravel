<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RoundlyConsulting\Qr\Contracts\Payload;
use RoundlyConsulting\Qr\DataTransferObjects\EncodeOptions;
use RoundlyConsulting\Qr\DataTransferObjects\PayloadRequirements;
use RoundlyConsulting\Qr\DataTransferObjects\QrOptions;
use RoundlyConsulting\Qr\DataTransferObjects\SvgOptions;
use RoundlyConsulting\Qr\Enums\DataUriEncoding;
use RoundlyConsulting\Qr\Enums\EciMode;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\FinderStyle;
use RoundlyConsulting\Qr\Enums\ModuleStyle;
use RoundlyConsulting\Qr\Enums\Segmentation;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;
use RoundlyConsulting\Qr\Support\ConfigGuard;
use RoundlyConsulting\Qr\ValueObjects\Color;
use RoundlyConsulting\Qr\ValueObjects\EncodingInfo;
use RoundlyConsulting\Qr\ValueObjects\QrMatrix;
use RoundlyConsulting\Qr\ValueObjects\Svg;
use Stringable;

/**
 * An immutable, fluent QR code under construction: every setter returns a modified copy.
 *
 * Precedence: explicit overrides (setters and withOptions(), last write wins) › the
 * payload's requirements › configuration. A payload's maximum version is a hard cap, and
 * options a payload locks (spec-mandated EPC/PAY by square settings, the sensitivity of a
 * 2FA seed) throw when overridden with a different value.
 */
final class PendingQr implements Htmlable, Responsable, Stringable
{
    private ?ErrorCorrection $errorCorrection = null;

    private ?int $minVersion = null;

    private ?int $maxVersion = null;

    private bool $maskSet = false;

    private ?int $mask = null;

    private ?bool $boost = null;

    private ?EciMode $eci = null;

    private ?Segmentation $segmentation = null;

    private ?bool $kanji = null;

    private bool $sizeSet = false;

    private ?int $size = null;

    private ?int $margin = null;

    private ?Color $foreground = null;

    private ?Color $background = null;

    private ?ModuleStyle $moduleStyle = null;

    private ?float $moduleRadius = null;

    private ?FinderStyle $finderStyle = null;

    private ?Color $finderColor = null;

    private ?string $title = null;

    private ?string $description = null;

    private ?bool $xmlDeclaration = null;

    private ?Sensitivity $sensitivity = null;

    public function __construct(
        private readonly QrManager $manager,
        private readonly Payload $payload,
    ) {}

    public function payload(): Payload
    {
        return $this->payload;
    }

    /**
     * @throws InvalidOptionException
     */
    public function errorCorrection(ErrorCorrection|string $level): self
    {
        $clone = clone $this;
        $clone->errorCorrection = ErrorCorrection::tryFromInput($level) ?? throw InvalidOptionException::errorCorrection();

        return $clone;
    }

    public function version(int $exact): self
    {
        return $this->versions($exact, $exact);
    }

    /**
     * @throws InvalidOptionException
     */
    public function versions(int $min, int $max): self
    {
        if ($min < 1 || $max > 40 || $min > $max) {
            throw InvalidOptionException::versionRange($min, $max);
        }

        $clone = clone $this;
        $clone->minVersion = $min;
        $clone->maxVersion = $max;

        return $clone;
    }

    /**
     * Force a mask (0–7), or null for the lowest-penalty mask.
     *
     * @throws InvalidOptionException
     */
    public function mask(?int $mask): self
    {
        if ($mask !== null && ($mask < 0 || $mask > 7)) {
            throw InvalidOptionException::mask($mask);
        }

        $clone = clone $this;
        $clone->maskSet = true;
        $clone->mask = $mask;

        return $clone;
    }

    public function boostErrorCorrection(bool $boost = true): self
    {
        $clone = clone $this;
        $clone->boost = $boost;

        return $clone;
    }

    public function eci(EciMode $mode): self
    {
        $clone = clone $this;
        $clone->eci = $mode;

        return $clone;
    }

    public function segmentation(Segmentation $strategy): self
    {
        $clone = clone $this;
        $clone->segmentation = $strategy;

        return $clone;
    }

    public function kanji(bool $enabled = true): self
    {
        $clone = clone $this;
        $clone->kanji = $enabled;

        return $clone;
    }

    /**
     * Rendered width/height in pixels, or null for a responsive symbol (viewBox only).
     *
     * @throws InvalidOptionException
     */
    public function size(?int $pixels): self
    {
        if ($pixels !== null && ($pixels < 1 || $pixels > SvgOptions::MAX_SIZE)) {
            throw InvalidOptionException::size($pixels);
        }

        $clone = clone $this;
        $clone->sizeSet = true;
        $clone->size = $pixels;

        return $clone;
    }

    /**
     * @throws InvalidOptionException
     */
    public function margin(int $modules): self
    {
        if ($modules < 0 || $modules > SvgOptions::MAX_MARGIN) {
            throw InvalidOptionException::margin($modules);
        }

        $clone = clone $this;
        $clone->margin = $modules;

        return $clone;
    }

    public function foreground(string $color): self
    {
        $clone = clone $this;
        $clone->foreground = Color::parse($color, 'foreground');

        return $clone;
    }

    /**
     * `transparent` omits the background rectangle.
     */
    public function background(string $color): self
    {
        $clone = clone $this;
        $clone->background = Color::parse($color, 'background');

        return $clone;
    }

    /**
     * @throws InvalidOptionException
     */
    public function moduleStyle(ModuleStyle $style, ?float $radius = null): self
    {
        if ($radius !== null && ($radius <= 0.0 || $radius > 0.5)) {
            throw InvalidOptionException::radius($radius);
        }

        $clone = clone $this;
        $clone->moduleStyle = $style;
        $clone->moduleRadius = $radius ?? $this->moduleRadius;

        return $clone;
    }

    public function finderStyle(FinderStyle $style, ?string $color = null): self
    {
        $clone = clone $this;
        $clone->finderStyle = $style;
        $clone->finderColor = $color === null ? $this->finderColor : Color::parse($color, 'finderColor');

        return $clone;
    }

    public function title(?string $title): self
    {
        $clone = clone $this;
        $clone->title = $title;

        return $clone;
    }

    public function description(?string $description): self
    {
        $clone = clone $this;
        $clone->description = $description;

        return $clone;
    }

    public function xmlDeclaration(bool $include = true): self
    {
        $clone = clone $this;
        $clone->xmlDeclaration = $include;

        return $clone;
    }

    /**
     * Override the payload's sensitivity (e.g. a payment code printed on a public invoice
     * page). 2FA seeds are locked at Secret.
     */
    public function sensitivity(Sensitivity $sensitivity): self
    {
        $clone = clone $this;
        $clone->sensitivity = $sensitivity;

        return $clone;
    }

    /**
     * Apply every non-null field of the options, as if each setter were called.
     */
    public function withOptions(QrOptions $options): self
    {
        $pending = $this;

        if ($options->size !== null) {
            $pending = $pending->size($options->size);
        }

        if ($options->margin !== null) {
            $pending = $pending->margin($options->margin);
        }

        if ($options->errorCorrection !== null) {
            $pending = $pending->errorCorrection($options->errorCorrection);
        }

        if ($options->minVersion !== null || $options->maxVersion !== null) {
            $min = $options->minVersion ?? $pending->minVersion;
            $max = $options->maxVersion ?? $pending->maxVersion;

            // A bound left unset comes from the configuration, narrowed towards the one that
            // was given so a single valid bound never conflicts with the configured window.
            $pending = $pending->versions(
                $min ?? min(ConfigGuard::minVersion(), $max ?? 40),
                $max ?? max(ConfigGuard::maxVersion(), $min ?? 1),
            );
        }

        if ($options->mask !== null) {
            $pending = $pending->mask($options->mask);
        }

        if ($options->foreground !== null) {
            $pending = $pending->foreground($options->foreground);
        }

        if ($options->background !== null) {
            $pending = $pending->background($options->background);
        }

        if ($options->moduleStyle !== null) {
            $pending = $pending->moduleStyle($options->moduleStyle);
        }

        if ($options->finderStyle !== null) {
            $pending = $pending->finderStyle($options->finderStyle);
        }

        if ($options->title !== null) {
            $pending = $pending->title($options->title);
        }

        if ($options->description !== null) {
            $pending = $pending->description($options->description);
        }

        if ($options->eci !== null) {
            $pending = $pending->eci($options->eci);
        }

        if ($options->sensitivity !== null) {
            $pending = $pending->sensitivity($options->sensitivity);
        }

        return $pending;
    }

    /**
     * @throws InvalidOptionException for an override of a locked option
     */
    public function matrix(): QrMatrix
    {
        return $this->manager->encode($this->payload->toQrString(), $this->encodeOptions(), $this->resolvedSensitivity());
    }

    public function info(): EncodingInfo
    {
        return $this->matrix()->info();
    }

    /**
     * Whether this code fits with its current settings — the version window, level, ECI policy,
     * segmentation and kanji switch `matrix()` would use — without building it.
     *
     * @throws InvalidOptionException for an override of a locked option
     */
    public function fits(): bool
    {
        return $this->manager->encoderFits($this->payload->toQrString(), $this->encodeOptions());
    }

    public function svg(): Svg
    {
        $encode = $this->encodeOptions();
        $sensitivity = $this->resolvedSensitivity();
        $data = $this->payload->toQrString();
        $translator = $this->manager->translator();

        return $this->manager->render(
            $data,
            fn (): QrMatrix => $this->manager->encode($data, $encode, $sensitivity),
            $encode,
            $this->svgOptions(),
            $this->title ?? (string) $translator->get('qr::qr.title'),
            $this->description ?? $this->payload->description($translator),
            $sensitivity,
        );
    }

    public function toDataUri(DataUriEncoding $encoding = DataUriEncoding::Percent): string
    {
        return $this->svg()->toDataUri($encoding);
    }

    public function toHtml(): string
    {
        return $this->svg()->toHtml();
    }

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        return $this->svg()->toResponse($request);
    }

    public function __toString(): string
    {
        return $this->svg()->toString();
    }

    private function encodeOptions(): EncodeOptions
    {
        $requirements = $this->payload->requirements();
        $type = class_basename($this->payload);

        $this->assertLocked($requirements, PayloadRequirements::ERROR_CORRECTION, $this->errorCorrection, $requirements->errorCorrection, $type);
        $this->assertLocked($requirements, PayloadRequirements::ECI, $this->eci, $requirements->eci, $type);
        $this->assertLocked($requirements, PayloadRequirements::SEGMENTATION, $this->segmentation, $requirements->segmentation, $type);
        $this->assertLocked($requirements, PayloadRequirements::BOOST_ERROR_CORRECTION, $this->boost, $requirements->boostErrorCorrection, $type);

        $maxVersion = $this->maxVersion ?? ConfigGuard::maxVersion();

        return new EncodeOptions(
            errorCorrection: $this->errorCorrection ?? $requirements->errorCorrection ?? ConfigGuard::errorCorrection(),
            minVersion: $this->minVersion ?? ConfigGuard::minVersion(),
            maxVersion: $requirements->maxVersion === null ? $maxVersion : min($maxVersion, $requirements->maxVersion),
            mask: $this->maskSet ? $this->mask : ConfigGuard::mask(),
            boostErrorCorrection: $this->boost ?? $requirements->boostErrorCorrection ?? ConfigGuard::boostErrorCorrection(),
            eci: $this->eci ?? $requirements->eci ?? ConfigGuard::eci(),
            segmentation: $this->segmentation ?? $requirements->segmentation ?? Segmentation::Optimal,
            kanji: $this->kanji ?? ConfigGuard::kanji(),
        );
    }

    private function svgOptions(): SvgOptions
    {
        return new SvgOptions(
            size: $this->sizeSet ? $this->size : ConfigGuard::svgSize(),
            margin: $this->margin ?? ConfigGuard::svgMargin(),
            foreground: $this->foreground ?? ConfigGuard::svgForeground(),
            background: $this->background ?? ConfigGuard::svgBackground(),
            moduleStyle: $this->moduleStyle ?? ConfigGuard::moduleStyle(),
            moduleRadius: $this->moduleRadius ?? ConfigGuard::moduleRadius(),
            finderStyle: $this->finderStyle ?? ConfigGuard::finderStyle(),
            finderColor: $this->finderColor ?? ConfigGuard::finderColor(),
            xmlDeclaration: $this->xmlDeclaration ?? ConfigGuard::xmlDeclaration(),
        );
    }

    private function resolvedSensitivity(): Sensitivity
    {
        $default = $this->payload->sensitivity();
        $requirements = $this->payload->requirements();

        $this->assertLocked($requirements, PayloadRequirements::SENSITIVITY, $this->sensitivity, $default, class_basename($this->payload));

        return $this->sensitivity ?? $default;
    }

    private function assertLocked(PayloadRequirements $requirements, string $option, mixed $override, mixed $required, string $type): void
    {
        if ($override !== null && $requirements->locks($option) && $override !== $required) {
            throw InvalidOptionException::lockedByPayload($option, $type);
        }
    }
}
