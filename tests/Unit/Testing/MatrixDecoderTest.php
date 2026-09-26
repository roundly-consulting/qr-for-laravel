<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\DataTransferObjects\EncodeOptions;
use RoundlyConsulting\Qr\Encoder\Bch;
use RoundlyConsulting\Qr\Encoder\Encoder;
use RoundlyConsulting\Qr\Encoder\FunctionPatterns;
use RoundlyConsulting\Qr\Encoder\Interleaver;
use RoundlyConsulting\Qr\Encoder\Masks;
use RoundlyConsulting\Qr\Encoder\Placement;
use RoundlyConsulting\Qr\Encoder\Segment;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\Mode;
use RoundlyConsulting\Qr\Exceptions\MatrixDecodeException;
use RoundlyConsulting\Qr\Testing\MatrixDecoder;
use RoundlyConsulting\Qr\ValueObjects\QrMatrix;

it('decodes a matrix or its rows with segment details', function (): void {
    $matrix = (new Encoder)->encodeSegments(
        [Segment::alphanumeric('ABC'), Segment::numeric('12345'), Segment::bytes('xyz')],
        new EncodeOptions(ErrorCorrection::Medium, mask: 3, boostErrorCorrection: false),
    );

    $decoded = MatrixDecoder::decode($matrix->rows());

    expect($decoded->bytes)->toBe('ABC12345xyz')
        ->and($decoded->version)->toBe(1)
        ->and($decoded->errorCorrection)->toBe(ErrorCorrection::Medium)
        ->and($decoded->mask)->toBe(3)
        ->and($decoded->eciDesignator)->toBeNull()
        ->and(array_map(static fn ($s): Mode => $s->mode, $decoded->segments))->toBe([Mode::Alphanumeric, Mode::Numeric, Mode::Byte])
        ->and(array_map(static fn ($s): int => $s->characterCount, $decoded->segments))->toBe([3, 5, 3]);
});

it('decodes multi-byte ECI designators', function (int $designator): void {
    $matrix = (new Encoder)->encodeSegments([Segment::eci($designator), Segment::bytes('a')]);

    expect(MatrixDecoder::decode($matrix)->eciDesignator)->toBe($designator);
})->with([3, 900, 20000]);

it('reads a version 7+ symbol and tolerates a damaged format copy', function (): void {
    $rows = (new Encoder)->encode(str_repeat('v', 120), new EncodeOptions(ErrorCorrection::Low, minVersion: 7))->rows();
    // Damage the first format copy beyond repair; the second copy still decodes.
    foreach ([0, 1, 2, 3, 4, 5] as $y) {
        $rows[$y][8] = $rows[$y][8] === '1' ? '0' : '1';
    }

    expect(MatrixDecoder::decode($rows)->bytes)->toBe(str_repeat('v', 120));
});

it('rejects malformed matrices', function (Closure $rows, string $reason): void {
    try {
        MatrixDecoder::decode($rows());
    } catch (MatrixDecodeException $e) {
        expect($e->reason)->toBe($reason);

        return;
    }

    $this->fail('expected a decode failure');
})->with([
    'too small' => [fn () => array_fill(0, 20, str_repeat('0', 20)), 'decode_size'],
    'wrong step' => [fn () => array_fill(0, 22, str_repeat('0', 22)), 'decode_size'],
    'ragged' => [fn () => [...array_fill(0, 20, str_repeat('0', 21)), '0'], 'decode_size'],
    'not binary' => [fn () => array_fill(0, 21, str_repeat('2', 21)), 'decode_size'],
    'no format' => [function (): array {
        $rows = (new Encoder)->encode('abc')->rows();

        // An all-light format word is at least five bits from every valid code word.
        foreach (FunctionPatterns::formatCoordinates(21) as $copy) {
            foreach ($copy as [$x, $y]) {
                $rows[$y][$x] = '0';
            }
        }

        return $rows;
    }, 'decode_format'],
    'bad version info' => [function (): array {
        $rows = (new Encoder)->encode(str_repeat('v', 120), new EncodeOptions(minVersion: 7))->rows();
        $size = count($rows);

        foreach (range(0, 5) as $b) {
            foreach (range(0, 2) as $a) {
                $rows[$b][$size - 11 + $a] = '0';
                $rows[$size - 11 + $a][$b] = '0';
            }
        }

        return $rows;
    }, 'decode_version'],
    'corrupted data' => [function (): array {
        $rows = (new Encoder)->encode('corrupt me', new EncodeOptions(mask: 0))->rows();
        $rows[20][20] = $rows[20][20] === '1' ? '0' : '1';

        return $rows;
    }, 'decode_error_correction'],
]);

it('rejects malformed segment streams', function (array $segments, string $message): void {
    $matrix = (new Encoder)->encodeSegments($segments, new EncodeOptions(ErrorCorrection::Low, minVersion: 1, maxVersion: 1, boostErrorCorrection: false));

    expect(fn () => MatrixDecoder::decode(tamperedSegments($matrix)))->toThrow(MatrixDecodeException::class, $message);
})->with([
    [[Segment::numeric('1')], 'unknown mode indicator'],
]);

/**
 * Re-encode a matrix after rewriting its first mode indicator to an undefined value (0b0011).
 *
 * @return list<string>
 */
function tamperedSegments(QrMatrix $matrix): array
{
    $data = [0b0011_0000, 0, 0xEC, 0x11, 0xEC, 0x11, 0xEC, 0x11, 0xEC, 0x11, 0xEC, 0x11, 0xEC, 0x11, 0xEC, 0x11, 0xEC, 0x11, 0xEC];

    return rowsForCodewords($data, $matrix->mask());
}

/**
 * Build version 1-L rows for arbitrary data codewords, bypassing the segment encoder.
 *
 * @param  list<int>  $data
 * @return list<string>
 */
function rowsForCodewords(array $data, int $mask = 0): array
{
    $codewords = Interleaver::interleave($data, 1, ErrorCorrection::Low);
    $grid = FunctionPatterns::template(1);
    Placement::place($grid, 1, $codewords);
    Masks::apply($grid, $mask);
    FunctionPatterns::drawFormat($grid, Bch::formatBits(ErrorCorrection::Low, $mask));

    return $grid->rows;
}

it('rejects out-of-range values inside segments', function (array $data, string $message): void {
    $padded = array_pad($data, 19, 0);

    expect(fn () => MatrixDecoder::decode(rowsForCodewords($padded)))->toThrow(MatrixDecodeException::class, $message);
})->with([
    // numeric, count 3, value 1000 (> 999)
    'numeric' => [[0b0001_0000, 0b0000_1111, 0b1110_1000, 0b0000_0000], 'numeric group'],
    // alphanumeric, count 1, value 63 (> 44)
    'alnum single' => [[0b0010_0000, 0b0000_1111, 0b1110_0000], 'alphanumeric value'],
    // alphanumeric, count 2, value 2047 (> 2024)
    'alnum pair' => [[0b0010_0000, 0b0001_0111, 0b1111_1111, 0b0000_0000], 'alphanumeric value'],
    // ECI with a 1111 designator prefix
    'eci' => [[0b0111_1111, 0b1000_0000], 'ECI designator'],
    // byte, count 200 — runs off the end of the stream
    'truncated' => [[0b0100_1100, 0b1000_0000], 'ends inside a segment'],
]);
