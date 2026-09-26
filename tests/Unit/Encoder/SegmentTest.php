<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Encoder\Segment;
use RoundlyConsulting\Qr\Enums\Mode;
use RoundlyConsulting\Qr\Exceptions\InvalidOptionException;
use RoundlyConsulting\Qr\Exceptions\UnencodableCharacterException;

it('packs numeric groups of three, two and one digit', function (): void {
    expect(Segment::numeric('01234567')->bits)->toBe('0000001100'.'0101011001'.'1000011')
        ->and(Segment::numeric('1')->bits)->toBe('0001')
        ->and(Segment::numeric('')->bits)->toBe('')
        ->and(Segment::numeric('12')->characterCount)->toBe(2);
});

it('packs alphanumeric pairs into 11 bits and a lone character into 6', function (): void {
    expect(Segment::alphanumeric('AC-42')->bits)->toBe('00111001110'.'11100111001'.'000010')
        ->and(Segment::alphanumeric('')->bits)->toBe('')
        ->and(Segment::alphanumeric('HELLO WORLD')->mode)->toBe(Mode::Alphanumeric);
});

it('packs bytes in eight bits each', function (): void {
    expect(Segment::bytes("\x00\xFF")->bits)->toBe('0000000011111111')
        ->and(Segment::bytes('')->characterCount)->toBe(0);
});

it('encodes ECI designators in one, two or three bytes', function (int $designator, string $bits): void {
    $segment = Segment::eci($designator);

    expect($segment->bits)->toBe($bits)
        ->and($segment->mode)->toBe(Mode::Eci)
        ->and($segment->eciDesignator)->toBe($designator)
        ->and($segment->bitLength(1))->toBe(4 + strlen($bits));
})->with([
    [26, '00011010'],
    [127, '01111111'],
    [128, '10'.'00000010000000'],
    [16383, '10'.'11111111111111'],
    [16384, '110'.'000000100000000000000'],
    [999999, '110'.'011110100001000111111'],
]);

it('rejects out-of-range ECI designators', function (int $designator): void {
    Segment::eci($designator);
})->throws(InvalidOptionException::class)->with([-1, 1_000_000]);

it('reports the position of an unencodable character', function (Closure $build, string $position): void {
    expect($build)->toThrow(UnencodableCharacterException::class, $position);
})->with([
    [fn () => Segment::numeric('12a4'), 'position 2'],
    [fn () => Segment::alphanumeric('ABc'), 'position 2'],
    [fn () => Segment::kanji('点a'), 'position 1'],
]);

it('measures bit length per version and detects count overflow', function (): void {
    $segment = Segment::bytes(str_repeat('a', 300));

    expect($segment->bitLength(9))->toBeNull()
        ->and($segment->bitLength(10))->toBe(4 + 16 + 2400)
        ->and(Segment::totalBits([$segment], 9))->toBeNull()
        ->and(Segment::totalBits([$segment, Segment::numeric('1')], 10))->toBe(2420 + 4 + 12 + 4)
        ->and(Segment::totalBits([], 1))->toBe(0);
});

it('classifies character sets', function (): void {
    expect(Segment::isNumeric('0123'))->toBeTrue()
        ->and(Segment::isNumeric('01a'))->toBeFalse()
        ->and(Segment::isAlphanumeric('HTTP://X.Y/$%*+-'))->toBeTrue()
        ->and(Segment::isAlphanumeric('lower'))->toBeFalse();
});
