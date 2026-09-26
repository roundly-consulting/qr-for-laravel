<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\DataTransferObjects\EncodeOptions;
use RoundlyConsulting\Qr\Encoder\Encoder;
use RoundlyConsulting\Qr\Encoder\Segment;
use RoundlyConsulting\Qr\Enums\EciMode;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\Mode;
use RoundlyConsulting\Qr\Enums\Segmentation;
use RoundlyConsulting\Qr\Exceptions\DataTooLongException;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;
use RoundlyConsulting\Qr\Testing\MatrixDecoder;
use RoundlyConsulting\Qr\ValueObjects\QrMatrix;

function encodeVerified(string $data, EncodeOptions $options = new EncodeOptions): QrMatrix
{
    $matrix = (new Encoder)->encode($data, $options);

    expect(MatrixDecoder::decode($matrix)->bytes)->toBe($data);

    return $matrix;
}

it('encodes the empty string as a valid version 1 symbol', function (): void {
    $matrix = encodeVerified('', new EncodeOptions(boostErrorCorrection: false));

    expect($matrix->version())->toBe(1)
        ->and($matrix->info()->segments)->toBe([])
        ->and($matrix->info()->dataBits)->toBe(0);
});

it('selects the smallest version that fits', function (int $bytes, ErrorCorrection $ecc, int $version): void {
    $matrix = encodeVerified(str_repeat('a', $bytes), new EncodeOptions($ecc, boostErrorCorrection: false));

    expect($matrix->version())->toBe($version);
})->with([
    'v1-L exact' => [17, ErrorCorrection::Low, 1],
    'v2-L one over' => [18, ErrorCorrection::Low, 2],
    'EPC limit v13-M' => [331, ErrorCorrection::Medium, 13],
    'v14 one over' => [332, ErrorCorrection::Medium, 14],
    'v40-L byte max' => [2953, ErrorCorrection::Low, 40],
]);

it('handles the character-count width change at versions 9/10 and 26/27', function (): void {
    // 230 bytes fit version 9-L data (232 codewords) with an 8-bit count, not 16.
    expect(encodeVerified(str_repeat('b', 229), new EncodeOptions(ErrorCorrection::Low, boostErrorCorrection: false))->version())->toBe(9)
        ->and(encodeVerified(str_repeat('b', 256), new EncodeOptions(ErrorCorrection::Low, boostErrorCorrection: false))->version())->toBe(10)
        ->and(encodeVerified(str_repeat('7', 3400), new EncodeOptions(ErrorCorrection::Low, boostErrorCorrection: false))->version())->toBeGreaterThan(26);
});

it('honours the version window', function (): void {
    expect(encodeVerified('hi', new EncodeOptions(minVersion: 5))->version())->toBe(5);

    (new Encoder)->encode(str_repeat('a', 332), new EncodeOptions(ErrorCorrection::Medium, maxVersion: 13));
})->throws(DataTooLongException::class);

it('reports the needed and available bits without the data', function (): void {
    try {
        (new Encoder)->encode(str_repeat('secret', 60), new EncodeOptions(ErrorCorrection::High, maxVersion: 5));
    } catch (DataTooLongException $e) {
        expect($e->neededBits)->toBeGreaterThan($e->capacityBits ?? 0)
            ->and($e->maxVersion)->toBe(5)
            ->and($e->errorCorrection)->toBe(ErrorCorrection::High)
            ->and($e->getMessage())->not->toContain('secret');

        return;
    }

    $this->fail('expected an exception');
});

it('reports needed bits even when the count field overflows', function (): void {
    (new Encoder)->encodeSegments([Segment::bytes(str_repeat('x', 300))], new EncodeOptions(maxVersion: 9));
})->throws(DataTooLongException::class, 'versions 1-9');

it('rejects input longer than any symbol before segmenting', function (): void {
    (new Encoder)->encode(str_repeat('1', 7090));
})->throws(DataTooLongException::class, '7090 bytes');

it('encodes the largest numeric payload', function (): void {
    expect(encodeVerified(str_repeat('9', 7089), new EncodeOptions(ErrorCorrection::Low))->version())->toBe(40);
});

it('boosts error correction without changing the version', function (): void {
    $plain = encodeVerified('HELLO', new EncodeOptions(ErrorCorrection::Low, boostErrorCorrection: false));
    $boosted = encodeVerified('HELLO', new EncodeOptions(ErrorCorrection::Low));

    expect($plain->errorCorrection())->toBe(ErrorCorrection::Low)
        ->and($plain->info()->errorCorrectionBoosted)->toBeFalse()
        ->and($boosted->version())->toBe($plain->version())
        ->and($boosted->errorCorrection())->toBe(ErrorCorrection::High)
        ->and($boosted->info()->errorCorrectionBoosted)->toBeTrue();
});

it('never lowers the requested level', function (): void {
    $matrix = encodeVerified(str_repeat('a', 1200), new EncodeOptions(ErrorCorrection::Quartile));

    expect($matrix->errorCorrection())->toBeIn([ErrorCorrection::Quartile, ErrorCorrection::High]);
});

it('forces a mask and skips scoring', function (): void {
    $matrix = encodeVerified('forced', new EncodeOptions(mask: 5));

    expect($matrix->mask())->toBe(5)
        ->and($matrix->info()->maskPenalties)->toBe([]);
});

it('chooses the mask with the lowest penalty, ties to the lowest index', function (): void {
    $info = encodeVerified('automatic mask selection')->info();

    expect($info->maskPenalties)->toHaveCount(8)
        ->and($info->mask)->toBe(array_search(min($info->maskPenalties), $info->maskPenalties, true));
});

it('rejects invalid options', function (Closure $build, string $message): void {
    expect($build)->toThrow(InvalidOptionException::class, $message);
})->with([
    [fn () => new EncodeOptions(minVersion: 0), 'version range'],
    [fn () => new EncodeOptions(maxVersion: 41), 'version range'],
    [fn () => new EncodeOptions(minVersion: 10, maxVersion: 9), 'version range'],
    [fn () => new EncodeOptions(mask: 8), 'mask'],
    [fn () => new EncodeOptions(mask: -1), 'mask'],
]);

it('prefixes ECI 26 only for non-ASCII UTF-8 in auto mode', function (): void {
    expect(encodeVerified('plain ascii')->info()->eciDesignator)->toBeNull()
        ->and(encodeVerified('Príspevok na kávu')->info()->eciDesignator)->toBe(26)
        ->and(encodeVerified("raw \xFF\xFE bytes")->info()->eciDesignator)->toBeNull()
        ->and(encodeVerified('ascii', new EncodeOptions(eci: EciMode::Always))->info()->eciDesignator)->toBe(26)
        ->and(encodeVerified('Žlté', new EncodeOptions(eci: EciMode::Never))->info()->eciDesignator)->toBeNull();

    $segments = encodeVerified('Žlté')->info()->segments;

    expect($segments[0]->mode)->toBe(Mode::Eci)
        ->and($segments[0]->bitLength)->toBe(12);
});

it('round-trips kanji segments', function (): void {
    $matrix = encodeVerified('点茗', new EncodeOptions(eci: EciMode::Never, kanji: true));

    expect($matrix->info()->segments[0]->mode)->toBe(Mode::Kanji)
        ->and($matrix->info()->segments[0]->bitLength)->toBe(4 + 8 + 26);
});

it('never combines kanji segments with an ECI designator', function (string $data, EciMode $eci, ?int $designator, array $modes): void {
    // Kanji segments carry Shift JIS; decoders disagree on whether an ECI 26 designator
    // re-interprets them, so the two are never mixed.
    $info = encodeVerified($data, new EncodeOptions(eci: $eci, kanji: true))->info();

    expect($info->eciDesignator)->toBe($designator)
        ->and(array_map(static fn ($segment): string => $segment->mode->key(), $info->segments))->toBe($modes);
})->with([
    'kanji only, auto' => ['日本語テキスト漢字', EciMode::Auto, null, ['kanji']],
    'kanji and ASCII, auto' => ['点茗 ABC 123', EciMode::Auto, null, ['kanji', 'alphanumeric']],
    'kanji and non-ASCII bytes, auto' => ['日本é語', EciMode::Auto, 26, ['eci', 'byte']],
    'kanji, always' => ['点茗', EciMode::Always, 26, ['eci', 'byte']],
    'kanji, never' => ['点茗', EciMode::Never, null, ['kanji']],
]);

it('encodes explicit segments and picks up their ECI designator', function (): void {
    $matrix = (new Encoder)->encodeSegments([Segment::eci(26), Segment::bytes('ž')]);

    expect($matrix->info()->eciDesignator)->toBe(26)
        ->and(MatrixDecoder::decode($matrix)->eciDesignator)->toBe(26);
});

it('honours the segmentation strategy', function (Segmentation $strategy, array $modes): void {
    $segments = encodeVerified('ABC1234567890abc', new EncodeOptions(segmentation: $strategy))->info()->segments;

    expect(array_map(static fn ($s): Mode => $s->mode, $segments))->toBe($modes);
})->with([
    [Segmentation::Byte, [Mode::Byte]],
    [Segmentation::Single, [Mode::Byte]],
    [Segmentation::Optimal, [Mode::Alphanumeric, Mode::Numeric, Mode::Byte]],
]);

it('produces identical matrices for identical input', function (): void {
    expect(encodeVerified('same')->equals(encodeVerified('same')))->toBeTrue()
        ->and(encodeVerified('same')->equals(encodeVerified('other')))->toBeFalse();
});
