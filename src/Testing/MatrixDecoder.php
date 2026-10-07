<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Testing;

use RoundlyConsulting\Qr\DataTransferObjects\DecodedQr;
use RoundlyConsulting\Qr\Encoder\Bch;
use RoundlyConsulting\Qr\Encoder\FunctionPatterns;
use RoundlyConsulting\Qr\Encoder\Interleaver;
use RoundlyConsulting\Qr\Encoder\Masks;
use RoundlyConsulting\Qr\Encoder\ModuleGrid;
use RoundlyConsulting\Qr\Encoder\Placement;
use RoundlyConsulting\Qr\Encoder\ReedSolomon;
use RoundlyConsulting\Qr\Encoder\Segment;
use RoundlyConsulting\Qr\Encoder\ShiftJis;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\Mode;
use RoundlyConsulting\Qr\Exceptions\MatrixDecodeException;
use RoundlyConsulting\Qr\ValueObjects\QrMatrix;
use RoundlyConsulting\Qr\ValueObjects\SegmentInfo;

/**
 * Reads a perfect module matrix back into its content — for tests, not for scanning
 * images. Format information must decode within the BCH code's correction radius, and
 * every Reed-Solomon block must have a zero syndrome: this decoder verifies, it does not
 * repair.
 */
final class MatrixDecoder
{
    /**
     * @param  QrMatrix|list<string>  $matrix  a matrix or its '0'/'1' rows
     *
     * @throws MatrixDecodeException
     */
    public static function decode(QrMatrix|array $matrix): DecodedQr
    {
        $rows = $matrix instanceof QrMatrix ? $matrix->rows() : $matrix;
        $size = count($rows);

        if ($size < 21 || $size > 177 || ($size - 17) % 4 !== 0) {
            throw MatrixDecodeException::size($size);
        }

        foreach ($rows as $row) {
            if (strlen($row) !== $size || strspn($row, '01') !== $size) {
                throw MatrixDecodeException::size($size);
            }
        }

        $version = intdiv($size - 17, 4);
        [$ecc, $mask] = self::readFormat($rows, $size);
        self::checkVersion($rows, $version);

        $grid = FunctionPatterns::template($version);
        $unmasked = new ModuleGrid($size, $rows, $grid->function);
        Masks::apply($unmasked, $mask);

        $data = [];

        foreach (Interleaver::deinterleave(Placement::read($unmasked->rows, $version), $version, $ecc) as $index => $block) {
            if (! ReedSolomon::isValid($block->codewords(), count($block->ecc))) {
                throw MatrixDecodeException::errorCorrection($index);
            }

            array_push($data, ...$block->data);
        }

        [$bytes, $segments, $eci] = self::parseSegments($data, $version);

        return new DecodedQr($bytes, $version, $ecc, $mask, $segments, $eci);
    }

    /**
     * @param  list<string>  $rows
     * @return array{0: ErrorCorrection, 1: int}
     */
    private static function readFormat(array $rows, int $size): array
    {
        $best = null;
        $bestDistance = 4;

        // Both copies are read; the closest valid code word within the BCH correction
        // radius (3) wins, so one damaged copy cannot mislead the decoder.
        foreach (FunctionPatterns::formatCoordinates($size) as $copy) {
            $bits = 0;

            foreach ($copy as $i => [$x, $y]) {
                $bits |= ($rows[$y][$x] === '1' ? 1 : 0) << $i;
            }

            foreach (ErrorCorrection::cases() as $ecc) {
                for ($mask = 0; $mask < 8; $mask++) {
                    $distance = Bch::hammingDistance($bits, Bch::formatBits($ecc, $mask));

                    if ($distance < $bestDistance) {
                        $best = [$ecc, $mask];
                        $bestDistance = $distance;
                    }
                }
            }
        }

        return $best ?? throw MatrixDecodeException::format();
    }

    /**
     * @param  list<string>  $rows
     */
    private static function checkVersion(array $rows, int $version): void
    {
        if ($version < 7) {
            return;
        }

        $size = count($rows);
        $expected = Bch::versionBits($version);
        $first = 0;
        $second = 0;

        for ($i = 0; $i < 18; $i++) {
            $a = $size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $first |= ($rows[$b][$a] === '1' ? 1 : 0) << $i;
            $second |= ($rows[$a][$b] === '1' ? 1 : 0) << $i;
        }

        if (Bch::hammingDistance($first, $expected) > 3 && Bch::hammingDistance($second, $expected) > 3) {
            throw MatrixDecodeException::version();
        }
    }

    /**
     * @param  list<int>  $codewords
     * @return array{0: string, 1: list<SegmentInfo>, 2: ?int}
     */
    private static function parseSegments(array $codewords, int $version): array
    {
        $bits = '';

        foreach ($codewords as $codeword) {
            $bits .= str_pad(decbin($codeword), 8, '0', STR_PAD_LEFT);
        }

        $length = strlen($bits);
        $position = 0;
        $bytes = '';
        $segments = [];
        $eci = null;

        $read = static function (int $count) use ($bits, $length, &$position): int {
            if ($position + $count > $length) {
                throw MatrixDecodeException::segment('the stream ends inside a segment');
            }

            $value = $count === 0 ? 0 : (int) bindec(substr($bits, $position, $count));
            $position += $count;

            return $value;
        };

        while ($length - $position >= 4) {
            $indicator = $read(4);

            if ($indicator === 0) {
                break;
            }

            $mode = Mode::tryFrom($indicator) ?? throw MatrixDecodeException::segment('unknown mode indicator');
            $start = $position;

            if ($mode === Mode::Eci) {
                $eci = self::readEci($read);
                $segments[] = new SegmentInfo($mode, 0, 4 + $position - $start);

                continue;
            }

            $count = $read($mode->charCountBits($version));

            $bytes .= match ($mode) {
                Mode::Numeric => self::readNumeric($read, $count),
                Mode::Alphanumeric => self::readAlphanumeric($read, $count),
                Mode::Kanji => self::readKanji($read, $count),
                default => self::readBytes($read, $count),
            };

            $segments[] = new SegmentInfo($mode, $count, 4 + $position - $start);
        }

        return [$bytes, $segments, $eci];
    }

    /**
     * @param  callable(int): int  $read
     */
    private static function readEci(callable $read): int
    {
        $first = $read(8);

        return match (true) {
            ($first & 0x80) === 0 => $first,
            ($first & 0xC0) === 0x80 => (($first & 0x3F) << 8) | $read(8),
            ($first & 0xE0) === 0xC0 => (($first & 0x1F) << 16) | $read(16),
            default => throw MatrixDecodeException::segment('invalid ECI designator'),
        };
    }

    /**
     * @param  callable(int): int  $read
     */
    private static function readNumeric(callable $read, int $count): string
    {
        $digits = '';

        for ($left = $count; $left > 0; $left -= 3) {
            $group = min(3, $left);
            $value = $read($group * 3 + 1);

            if ($value >= 10 ** $group) {
                throw MatrixDecodeException::segment('numeric group out of range');
            }

            $digits .= str_pad((string) $value, $group, '0', STR_PAD_LEFT);
        }

        return $digits;
    }

    /**
     * @param  callable(int): int  $read
     */
    private static function readAlphanumeric(callable $read, int $count): string
    {
        $text = '';

        for ($left = $count; $left > 0; $left -= 2) {
            if ($left === 1) {
                $value = $read(6);

                if ($value >= 45) {
                    throw MatrixDecodeException::segment('alphanumeric value out of range');
                }

                $text .= Segment::ALPHANUMERIC_CHARSET[$value];

                continue;
            }

            $value = $read(11);

            if ($value >= 45 * 45) {
                throw MatrixDecodeException::segment('alphanumeric value out of range');
            }

            $text .= Segment::ALPHANUMERIC_CHARSET[intdiv($value, 45)].Segment::ALPHANUMERIC_CHARSET[$value % 45];
        }

        return $text;
    }

    /**
     * @param  callable(int): int  $read
     */
    private static function readKanji(callable $read, int $count): string
    {
        $text = '';

        for ($i = 0; $i < $count; $i++) {
            $value = $read(13);
            $character = ShiftJis::fromKanjiValue($value);

            // mbstring substitutes "?" for a code that is no Shift JIS kanji (a trail byte
            // outside 0x40–0xFC, an unmapped code): only a value that converts both ways is
            // one the encoder could have written.
            if (ShiftJis::kanjiValue($character) !== $value) {
                throw MatrixDecodeException::segment('kanji value out of range');
            }

            $text .= $character;
        }

        return $text;
    }

    /**
     * @param  callable(int): int  $read
     */
    private static function readBytes(callable $read, int $count): string
    {
        $bytes = '';

        for ($i = 0; $i < $count; $i++) {
            $bytes .= chr($read(8));
        }

        return $bytes;
    }
}
