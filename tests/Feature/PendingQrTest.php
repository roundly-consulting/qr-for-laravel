<?php

declare(strict_types=1);

use Illuminate\Contracts\Translation\Translator;
use RoundlyConsulting\Qr\Contracts\Payload;
use RoundlyConsulting\Qr\DataTransferObjects\PayloadRequirements;
use RoundlyConsulting\Qr\DataTransferObjects\QrOptions;
use RoundlyConsulting\Qr\Enums\EciMode;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\FinderStyle;
use RoundlyConsulting\Qr\Enums\ModuleStyle;
use RoundlyConsulting\Qr\Enums\Segmentation;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Exceptions\DataTooLongException;
use RoundlyConsulting\Qr\Exceptions\InvalidColorException;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;
use RoundlyConsulting\Qr\Facades\Qr;
use RoundlyConsulting\Qr\Tests\Support\PathRasterizer;

/**
 * A host payload that caps the version and locks two spec options, like a payment format.
 */
function lockedPayload(): Payload
{
    return new class implements Payload
    {
        public function toQrString(): string
        {
            return 'LOCKED PAYLOAD 123';
        }

        public function requirements(): PayloadRequirements
        {
            return new PayloadRequirements(
                errorCorrection: ErrorCorrection::Medium,
                maxVersion: 2,
                segmentation: Segmentation::Byte,
                eci: EciMode::Never,
                boostErrorCorrection: false,
                locked: [PayloadRequirements::ERROR_CORRECTION, PayloadRequirements::ECI, PayloadRequirements::SEGMENTATION, PayloadRequirements::BOOST_ERROR_CORRECTION],
            );
        }

        public function sensitivity(): Sensitivity
        {
            return Sensitivity::Personal;
        }

        public function description(Translator $translator): ?string
        {
            return null;
        }
    };
}

it('is immutable', function (): void {
    $base = Qr::text('hello');
    $sized = $base->size(100);

    expect($sized)->not->toBe($base)
        ->and($base->svg()->width())->toBe(256)
        ->and($sized->svg()->width())->toBe(100);
});

it('applies every builder option', function (): void {
    $pending = Qr::text('ABC123abc')
        ->errorCorrection('q')
        ->versions(3, 10)
        ->mask(6)
        ->boostErrorCorrection(false)
        ->eci(EciMode::Always)
        ->segmentation(Segmentation::Single)
        ->kanji()
        ->size(null)
        ->margin(2)
        ->foreground('#112233')
        ->background('transparent')
        ->moduleStyle(ModuleStyle::Rounded, 0.3)
        ->finderStyle(FinderStyle::Rounded, 'navy')
        ->title('T')
        ->description('D');

    $info = $pending->info();
    $svg = $pending->svg()->toString();

    expect($info->version)->toBe(3)
        ->and($info->errorCorrection)->toBe(ErrorCorrection::Quartile)
        ->and($info->mask)->toBe(6)
        ->and($info->eciDesignator)->toBe(26)
        ->and($info->errorCorrectionBoosted)->toBeFalse()
        ->and(count($info->segments))->toBe(2)
        ->and($svg)->toContain('viewBox="0 0 33 33"')
        ->and($svg)->not->toContain('width=')
        ->and($svg)->not->toContain('<rect')
        ->and($svg)->toContain('fill="#112233"')
        ->and($svg)->toContain('fill="navy"')
        ->and($svg)->toContain('a0.3 0.3')
        ->and($svg)->toContain('<title>T</title><desc>D</desc>');

    expect(Qr::text('x')->version(7)->info()->version)->toBe(7)
        ->and(Qr::text('x')->mask(2)->mask(null)->info()->maskPenalties)->toHaveCount(8)
        ->and(Qr::text('x')->moduleStyle(ModuleStyle::Dots)->finderStyle(FinderStyle::Square)->svg()->toString())->toContain('a0.5 0.5 0 1 0');
});

it('falls back to configuration', function (): void {
    config(['qr.error_correction' => 'L', 'qr.boost_error_correction' => false, 'qr.svg.size' => null, 'qr.svg.margin' => 1, 'qr.mask' => 4]);

    $info = Qr::text('hello')->info();

    expect($info->errorCorrection)->toBe(ErrorCorrection::Low)
        ->and($info->mask)->toBe(4)
        ->and(Qr::text('hello')->svg()->width())->toBeNull()
        ->and(Qr::text('hello')->svg()->viewBoxSize())->toBe(23);
});

it('lets overrides win over configuration in call order', function (): void {
    config(['qr.error_correction' => 'L']);

    $pending = Qr::text('hello')
        ->withOptions(new QrOptions(errorCorrection: ErrorCorrection::High, size: 50))
        ->errorCorrection(ErrorCorrection::Quartile)
        ->boostErrorCorrection(false);

    expect($pending->info()->errorCorrection)->toBe(ErrorCorrection::Quartile)
        ->and($pending->svg()->width())->toBe(50)
        ->and(Qr::text('hello')->errorCorrection('Q')->withOptions(new QrOptions(errorCorrection: ErrorCorrection::High))->info()->errorCorrection)->toBe(ErrorCorrection::High);
});

it('applies every QrOptions field and skips null ones', function (): void {
    $svg = Qr::text('hello')->withOptions(new QrOptions(
        size: 90, margin: 3, errorCorrection: ErrorCorrection::Low, minVersion: 2, maxVersion: 5, mask: 1,
        foreground: 'red', background: '#eee', moduleStyle: ModuleStyle::Rounded, finderStyle: FinderStyle::Rounded,
        title: 'Title', description: 'Desc', eci: EciMode::Never, sensitivity: Sensitivity::Personal,
    ))->svg();

    expect($svg->width())->toBe(90)
        ->and($svg->viewBoxSize())->toBe(25 + 6)
        ->and($svg->matrix()->mask())->toBe(1)
        ->and($svg->sensitivity())->toBe(Sensitivity::Personal)
        ->and($svg->toString())->toContain('fill="red"')->toContain('fill="#eee"')->toContain('<desc>Desc</desc>')
        ->and(Qr::text('x')->versions(4, 9)->withOptions(new QrOptions(maxVersion: 6))->info()->version)->toBe(4)
        ->and(Qr::text('x')->withOptions(new QrOptions)->svg()->width())->toBe(256);
});

it('uses payload requirements and caps the version', function (): void {
    $info = Qr::make(lockedPayload())->info();

    expect($info->errorCorrection)->toBe(ErrorCorrection::Medium)
        ->and($info->errorCorrectionBoosted)->toBeFalse()
        ->and($info->segments[0]->mode->key())->toBe('byte')
        ->and(Qr::make(lockedPayload())->svg()->toString())->not->toContain('<desc>');

    Qr::make(lockedPayload())->versions(3, 40)->info();
})->throws(InvalidOptionException::class, 'version range');

it('throws when the data exceeds a payload cap', function (): void {
    $payload = new class implements Payload
    {
        public function toQrString(): string
        {
            return str_repeat('a', 100);
        }

        public function requirements(): PayloadRequirements
        {
            return new PayloadRequirements(maxVersion: 2);
        }

        public function sensitivity(): Sensitivity
        {
            return Sensitivity::Public;
        }

        public function description(Translator $translator): ?string
        {
            return null;
        }
    };

    Qr::make($payload)->info();
})->throws(DataTooLongException::class);

it('refuses to override a locked option with a different value', function (Closure $override, string $option): void {
    expect(fn () => $override(Qr::make(lockedPayload()))->matrix())->toThrow(InvalidOptionException::class, "[{$option}]");
})->with([
    [fn ($p) => $p->errorCorrection(ErrorCorrection::High), 'errorCorrection'],
    [fn ($p) => $p->eci(EciMode::Always), 'eci'],
    [fn ($p) => $p->segmentation(Segmentation::Optimal), 'segmentation'],
    [fn ($p) => $p->boostErrorCorrection(), 'boostErrorCorrection'],
]);

it('accepts a locked option set to its required value', function (): void {
    expect(Qr::make(lockedPayload())->errorCorrection('M')->eci(EciMode::Never)->boostErrorCorrection(false)->matrix()->version())->toBe(2);
});

it('validates builder input immediately', function (Closure $call, string $exception): void {
    expect($call)->toThrow($exception);
})->with([
    [fn () => Qr::text('x')->errorCorrection('Z'), InvalidOptionException::class],
    [fn () => Qr::text('x')->versions(0, 3), InvalidOptionException::class],
    [fn () => Qr::text('x')->versions(5, 4), InvalidOptionException::class],
    [fn () => Qr::text('x')->version(41), InvalidOptionException::class],
    [fn () => Qr::text('x')->mask(8), InvalidOptionException::class],
    [fn () => Qr::text('x')->size(0), InvalidOptionException::class],
    [fn () => Qr::text('x')->margin(65), InvalidOptionException::class],
    [fn () => Qr::text('x')->moduleStyle(ModuleStyle::Dots, 0.9), InvalidOptionException::class],
    [fn () => Qr::text('x')->foreground('red" onload="x'), InvalidColorException::class],
    [fn () => Qr::text('x')->background('url(#x)'), InvalidColorException::class],
    [fn () => Qr::text('x')->finderStyle(FinderStyle::Square, 'var(--x)'), InvalidColorException::class],
]);

it('renders a matrix-faithful path through the builder', function (): void {
    $pending = Qr::url('https://example.com/pay?invoice=2026-0042')->margin(2);
    $svg = $pending->svg();

    expect(PathRasterizer::rows(PathRasterizer::pathData($svg->toString()), $svg->matrix()->size(), 2))->toBe($svg->matrix()->rows());
});

it('keeps the configured other version bound when QrOptions sets only one', function (): void {
    config(['qr.versions.min' => 5, 'qr.versions.max' => 12]);

    expect(fn () => Qr::text(str_repeat('x', 300))->withOptions(new QrOptions(minVersion: 10))->info())
        ->toThrow(DataTooLongException::class, 'versions 10-12')
        ->and(Qr::text('x')->withOptions(new QrOptions(maxVersion: 8))->info()->version)->toBe(5)
        ->and(Qr::text('x')->withOptions(new QrOptions(minVersion: 7))->info()->version)->toBe(7)
        // A single bound outside the configured window narrows it instead of throwing.
        ->and(Qr::text('x')->withOptions(new QrOptions(maxVersion: 3))->info()->version)->toBe(3)
        ->and(Qr::text('x')->withOptions(new QrOptions(minVersion: 20))->info()->version)->toBe(20)
        ->and(Qr::text('x')->versions(6, 9)->withOptions(new QrOptions(maxVersion: 8))->info()->version)->toBe(6);
});
