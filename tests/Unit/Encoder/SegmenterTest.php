<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Encoder\Segment;
use RoundlyConsulting\Qr\Encoder\Segmenter;
use RoundlyConsulting\Qr\Enums\Mode;
use RoundlyConsulting\Qr\Enums\Segmentation;

/**
 * @param  list<Segment>  $segments
 * @return list<string>
 */
function modesOf(array $segments): array
{
    return array_map(static fn (Segment $s): string => $s->mode->key().':'.$s->characterCount, $segments);
}

it('returns no segment for empty input', function (Segmentation $strategy): void {
    expect(Segmenter::segment('', $strategy, 1))->toBe([]);
})->with(Segmentation::cases());

it('keeps raw bytes in one byte segment', function (): void {
    expect(modesOf(Segmenter::segment('12345', Segmentation::Byte, 1)))->toBe(['byte:5']);
});

it('picks the tightest single mode', function (string $data, string $mode, bool $kanji): void {
    expect(Segmenter::single($data, $kanji)->mode->key())->toBe($mode);
})->with([
    ['0123', 'numeric', false],
    ['HELLO WORLD', 'alphanumeric', false],
    ['Hello', 'byte', false],
    ['点茗', 'kanji', true],
    ['点茗', 'byte', false],
    ['点a', 'byte', true],
]);

it('splits mixed text into the shortest bit stream', function (): void {
    $segments = Segmenter::optimal('ABC1234567890abc', 1);

    expect(modesOf($segments))->toBe(['alphanumeric:3', 'numeric:10', 'byte:3'])
        ->and(Segment::totalBits($segments, 1))->toBe((4 + 9 + 11 + 6) + (4 + 10 + 34) + (4 + 8 + 24));
});

it('never does worse than the best single mode', function (string $data): void {
    foreach ([1, 10, 27] as $version) {
        $optimal = Segment::totalBits(Segmenter::optimal($data, $version), $version);
        $single = Segment::totalBits([Segmenter::single($data)], $version);

        expect($optimal)->toBeLessThanOrEqual($single);
    }
})->with([
    'short digits in text' => ['abc12def'],
    'url' => ['https://EXAMPLE.COM/PATH/12345678901234567890'],
    'utf-8' => ['Žltý kôň 2026 ÁČ'],
    'digits' => ['12345678901234567890'],
]);

it('does not split off a run too short to pay for its header', function (): void {
    expect(modesOf(Segmenter::optimal('a1b', 1)))->toBe(['byte:3']);
});

it('prices segment headers per version group', function (): void {
    $data = str_repeat('A', 8).'123456'.str_repeat('a', 4);

    expect(modesOf(Segmenter::optimal($data, 1)))->not->toBe([])
        ->and(Segment::totalBits(Segmenter::optimal($data, 40), 40))
        ->toBeGreaterThanOrEqual(Segment::totalBits(Segmenter::optimal($data, 1), 1));
});

it('uses kanji mode only when enabled', function (): void {
    expect(modesOf(Segmenter::optimal('点茗x', 1, kanji: true)))->toBe(['kanji:2', 'byte:1'])
        ->and(modesOf(Segmenter::optimal('点茗x', 1)))->toBe(['byte:7']);
});

it('falls back to one byte segment for invalid UTF-8', function (): void {
    $segments = Segmenter::optimal("12\xFF\xFE34", 1);

    expect($segments)->toHaveCount(1)
        ->and($segments[0]->mode)->toBe(Mode::Byte);
});
