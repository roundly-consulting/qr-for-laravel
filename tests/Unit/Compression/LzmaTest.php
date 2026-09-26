<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Compression\Lzma\LzmaDecoder;
use RoundlyConsulting\Qr\Compression\Lzma\LzmaEncoder;
use RoundlyConsulting\Qr\Compression\Lzma\LzmaModel;
use RoundlyConsulting\Qr\Compression\Lzma\LzmaProperties;
use RoundlyConsulting\Qr\Compression\Lzma\RangeDecoder;
use RoundlyConsulting\Qr\Compression\Lzma\RangeEncoder;
use RoundlyConsulting\Qr\Exceptions\CorruptLzmaStreamException;
use RoundlyConsulting\Qr\Tests\Support\Corpus;

it('describes the PAY by square stream properties', function (): void {
    $properties = new LzmaProperties;

    expect($properties->byte())->toBe(0x5D)
        ->and(bin2hex($properties->header()))->toBe('5d00000200ffffffffffffffff')
        ->and(bin2hex($properties->header(5)))->toBe('5d000002000500000000000000');
});

it('maps distances to position slots', function (int $distance, int $slot): void {
    expect(LzmaModel::positionSlot($distance))->toBe($slot);
})->with([[0, 0], [3, 3], [4, 4], [5, 4], [6, 5], [7, 5], [8, 6], [127, 13], [128, 14], [0xFFFFFFFF, 63]]);

it('walks the state machine per the specification', function (): void {
    expect(array_map(LzmaModel::afterLiteral(...), range(0, 11)))->toBe([0, 0, 0, 0, 1, 2, 3, 4, 5, 6, 4, 5])
        ->and(array_map(LzmaModel::afterMatch(...), [0, 6, 7, 11]))->toBe([7, 7, 10, 10])
        ->and(array_map(LzmaModel::afterRep(...), [0, 6, 7, 11]))->toBe([8, 8, 11, 11])
        ->and(array_map(LzmaModel::afterShortRep(...), [0, 6, 7, 11]))->toBe([9, 9, 11, 11]);
});

it('round-trips bits, trees and direct bits through the range coder', function (): void {
    mt_srand(99);
    $p = array_fill(0, 64, LzmaModel::PROB_INIT);
    $q = $p;
    $encoder = new RangeEncoder;
    $values = [];

    for ($i = 0; $i < 500; $i++) {
        $values[] = [mt_rand(0, 1), mt_rand(0, 31), mt_rand(0, 15), mt_rand(0, (1 << 26) - 1)];
    }

    foreach ($values as [$bit, $tree, $reverse, $direct]) {
        $encoder->bit($p, 0, $bit);
        $encoder->bitTree($p, 1, 5, $tree);
        $encoder->reverseBitTree($p, 33, 4, $reverse);
        $encoder->directBits($direct, 26);
    }

    $decoder = new RangeDecoder($encoder->finish());

    foreach ($values as [$bit, $tree, $reverse, $direct]) {
        expect($decoder->bit($q, 0))->toBe($bit)
            ->and($decoder->bitTree($q, 1, 5))->toBe($tree)
            ->and($decoder->reverseBitTree($q, 33, 4))->toBe($reverse)
            ->and($decoder->directBits(26))->toBe($direct);
    }

    expect($decoder->isFinishedOk())->toBeTrue();
});

it('round-trips the corpus and ends every stream with the end marker', function (string $name): void {
    $input = Corpus::all()[$name];
    $stream = LzmaEncoder::encode($input);

    expect($stream[0])->toBe("\x00")
        ->and(LzmaDecoder::decode($stream, strlen($input)))->toBe($input)
        ->and(LzmaEncoder::encode($input))->toBe($stream);

    // Decoding one byte further must hit the end marker, proving it is there.
    expect(fn () => LzmaDecoder::decode($stream, strlen($input) + 1))->toThrow(CorruptLzmaStreamException::class, 'end marker');
})->with(array_keys(Corpus::all()));

it('compresses repetitive data', function (): void {
    expect(strlen(LzmaEncoder::encode(str_repeat('a', 10000))))->toBeLessThan(100);
});

it('rejects corrupt streams', function (Closure $decode, string $message): void {
    expect($decode)->toThrow(CorruptLzmaStreamException::class, $message);
})->with([
    'short header' => [fn () => LzmaDecoder::decode("\x00\x00", 1), 'header'],
    'non-zero first byte' => [fn () => LzmaDecoder::decode("\x01\x00\x00\x00\x00\x00", 1), 'header'],
    'code equals range' => [fn () => LzmaDecoder::decode("\x00\xFF\xFF\xFF\xFF\x00", 1), 'header'],
    'truncated' => [fn () => LzmaDecoder::decode(substr(LzmaEncoder::encode(str_repeat('abc', 500)), 0, 8), 1500), 'ends before'],
    'negative length' => [fn () => LzmaDecoder::decode(LzmaEncoder::encode('x'), -1), 'negative'],
    'match past length' => [fn () => LzmaDecoder::decode(LzmaEncoder::encode(str_repeat('a', 100)), 50), 'past the declared length'],
]);

it('rejects distances that point before the data', function (): void {
    // Hand-built stream: a normal match (distance 0) as the very first symbol.
    $p = LzmaModel::probabilities(new LzmaProperties);
    $encoder = new RangeEncoder;
    $encoder->bit($p, LzmaModel::IS_MATCH, 1);
    $encoder->bit($p, LzmaModel::IS_REP, 0);
    $encoder->bit($p, LzmaModel::LEN + LzmaModel::LEN_CHOICE, 0);
    $encoder->bitTree($p, LzmaModel::LEN + LzmaModel::LEN_LOW, 3, 0);
    $encoder->bitTree($p, LzmaModel::POS_SLOT, 6, 0);

    expect(fn () => LzmaDecoder::decode($encoder->finish(), 2))->toThrow(CorruptLzmaStreamException::class, 'before the start');
});

it('rejects a repeated match before any output', function (): void {
    $p = LzmaModel::probabilities(new LzmaProperties);
    $encoder = new RangeEncoder;
    $encoder->bit($p, LzmaModel::IS_MATCH, 1);
    $encoder->bit($p, LzmaModel::IS_REP, 1);

    expect(fn () => LzmaDecoder::decode($encoder->finish(), 2))->toThrow(CorruptLzmaStreamException::class, 'precedes any output');
});

it('stops at the declared length without needing the end marker', function (): void {
    $stream = LzmaEncoder::encode('hello world');

    expect(LzmaDecoder::decode($stream, 5))->toBe('hello')
        ->and(LzmaDecoder::decode($stream, 0))->toBe('');
});
