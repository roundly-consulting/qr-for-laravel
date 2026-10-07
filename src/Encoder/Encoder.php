<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Encoder;

use Closure;
use RoundlyConsulting\Qr\DataTransferObjects\EncodeOptions;
use RoundlyConsulting\Qr\Enums\EciMode;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\Mode;
use RoundlyConsulting\Qr\Exceptions\DataTooLongException;
use RoundlyConsulting\Qr\ValueObjects\EncodingInfo;
use RoundlyConsulting\Qr\ValueObjects\QrMatrix;
use RoundlyConsulting\Qr\ValueObjects\SegmentInfo;

/**
 * QR Code Model 2 encoder (ISO/IEC 18004:2015): segmentation, version selection,
 * error-correction boost, bit stream, Reed-Solomon codewords and interleaving, module
 * placement, mask selection and format/version information. Framework-free and stateless
 * apart from input-independent lookup tables.
 */
final class Encoder
{
    /** The largest character count any symbol holds (version 40-L, numeric). */
    public const int MAX_INPUT_BYTES = 7089;

    /**
     * @throws DataTooLongException
     */
    public function encode(string $data, EncodeOptions $options = new EncodeOptions): QrMatrix
    {
        if (strlen($data) > self::MAX_INPUT_BYTES) {
            throw DataTooLongException::input(strlen($data), self::MAX_INPUT_BYTES);
        }

        return $this->build($this->segmenter($data, $options), $options);
    }

    /**
     * Whether `encode()` would find a version for the data — the same input limit, segments,
     * ECI header and version window — without building the symbol (no error correction,
     * placement or mask evaluation), so it is cheap enough for validation.
     */
    public function fits(string $data, EncodeOptions $options = new EncodeOptions): bool
    {
        if (strlen($data) > self::MAX_INPUT_BYTES) {
            return false;
        }

        try {
            $this->selectVersion($this->segmenter($data, $options), $options);
        } catch (DataTooLongException) {
            return false;
        }

        return true;
    }

    /**
     * The per-version segment builder; segments only change between the three
     * character-count bands (1–9, 10–26, 27–40), so each band is segmented once.
     *
     * @return Closure(int): list<Segment>
     */
    private function segmenter(string $data, EncodeOptions $options): Closure
    {
        $groups = [];

        return function (int $version) use ($data, $options, &$groups): array {
            $group = $version <= 9 ? 0 : ($version <= 26 ? 1 : 2);

            return $groups[$group] ??= $this->segmentsWithEci($data, $options, $version);
        };
    }

    /**
     * Encode explicit segments, bypassing segmentation and the ECI policy.
     *
     * @param  list<Segment>  $segments
     *
     * @throws DataTooLongException
     */
    public function encodeSegments(array $segments, EncodeOptions $options = new EncodeOptions): QrMatrix
    {
        return $this->build(static fn (int $version): array => $segments, $options);
    }

    /**
     * @param  Closure(int): list<Segment>  $segmentsFor
     */
    private function build(Closure $segmentsFor, EncodeOptions $options): QrMatrix
    {
        [$version, $segments, $usedBits] = $this->selectVersion($segmentsFor, $options);
        $eci = null;

        foreach ($segments as $segment) {
            $eci ??= $segment->eciDesignator;
        }

        $ecc = $options->errorCorrection;
        $boosted = false;

        while ($options->boostErrorCorrection && ($stronger = $ecc->stronger()) !== null && $usedBits <= Capacity::dataBits($version, $stronger)) {
            $ecc = $stronger;
            $boosted = true;
        }

        $codewords = Interleaver::interleave($this->dataCodewords($segments, $version, $ecc), $version, $ecc);

        $grid = FunctionPatterns::template($version);
        Placement::place($grid, $version, $codewords);

        [$mask, $penalties] = $this->chooseMask($grid, $ecc, $options->mask);

        Masks::apply($grid, $mask);
        FunctionPatterns::drawFormat($grid, Bch::formatBits($ecc, $mask));

        $info = new EncodingInfo(
            version: $version,
            errorCorrection: $ecc,
            mask: $mask,
            segments: array_map(
                static fn (Segment $segment): SegmentInfo => new SegmentInfo($segment->mode, $segment->characterCount, (int) $segment->bitLength($version)),
                $segments,
            ),
            dataBits: $usedBits,
            capacityBits: Capacity::dataBits($version, $ecc),
            eciDesignator: $eci,
            maskPenalties: $penalties,
            errorCorrectionBoosted: $boosted,
        );

        return new QrMatrix(array_values($grid->rows), $info);
    }

    /**
     * The smallest version in the window whose data capacity fits the segments.
     *
     * @param  Closure(int): list<Segment>  $segmentsFor
     * @return array{0: int, 1: list<Segment>, 2: int}
     */
    private function selectVersion(Closure $segmentsFor, EncodeOptions $options): array
    {
        $ecc = $options->errorCorrection;

        for ($version = $options->minVersion; $version <= $options->maxVersion; $version++) {
            $segments = $segmentsFor($version);
            $bits = Segment::totalBits($segments, $version);

            if ($bits !== null && $bits <= Capacity::dataBits($version, $ecc)) {
                return [$version, $segments, $bits];
            }
        }

        $max = $options->maxVersion;
        $segments = $segmentsFor($max);
        // A count that overflows the largest version's count field is measured at the next
        // wider field that holds it, so the figure still includes every count indicator.
        $needed = Segment::totalBits($segments, $max) ?? array_sum(array_map(
            static fn (Segment $segment): int => $segment->bitLength($max)
                ?? $segment->bitLength(10)
                ?? $segment->bitLength(27)
                ?? 4 + $segment->mode->charCountBits(27) + strlen($segment->bits),
            $segments,
        ));

        throw DataTooLongException::capacity($needed, Capacity::dataBits($max, $ecc), $options->minVersion, $max, $ecc);
    }

    /**
     * Segments, terminator, zero bits to the byte boundary and alternating pad codewords
     * (ISO §7.4.9–7.4.10).
     *
     * @param  list<Segment>  $segments
     * @return list<int>
     */
    private function dataCodewords(array $segments, int $version, ErrorCorrection $ecc): array
    {
        $buffer = new BitBuffer;

        foreach ($segments as $segment) {
            $buffer->append($segment->mode->value, 4);
            $buffer->append($segment->characterCount, $segment->mode->charCountBits($version));
            $buffer->appendBits($segment->bits);
        }

        $capacity = Capacity::dataBits($version, $ecc);
        $buffer->append(0, min(4, $capacity - $buffer->length()));
        $buffer->append(0, (8 - $buffer->length() % 8) % 8);

        for ($pad = 0xEC; $buffer->length() < $capacity; $pad ^= 0xEC ^ 0x11) {
            $buffer->append($pad, 8);
        }

        return $buffer->toCodewords();
    }

    /**
     * A forced mask, or the one with the lowest penalty (ties → lowest index).
     *
     * @return array{0: int, 1: list<int>}
     */
    private function chooseMask(ModuleGrid $grid, ErrorCorrection $ecc, ?int $forced): array
    {
        if ($forced !== null) {
            return [$forced, []];
        }

        $penalties = [];

        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = clone $grid;
            Masks::apply($candidate, $mask);
            FunctionPatterns::drawFormat($candidate, Bch::formatBits($ecc, $mask));
            $penalties[] = MaskEvaluator::penalty($candidate->rows);
        }

        $best = array_search(min($penalties), $penalties, true);

        return [(int) $best, $penalties];
    }

    /**
     * The segments for one count-field group with the ECI policy applied. `Auto` prefixes
     * ECI 26 only when a byte segment carries non-ASCII UTF-8. Kanji segments hold Shift JIS
     * values and decoders disagree on whether an ECI designator re-interprets them, so when a
     * designator is emitted the input is segmented again without kanji.
     *
     * @return list<Segment>
     */
    private function segmentsWithEci(string $data, EncodeOptions $options, int $version): array
    {
        $segments = Segmenter::segment($data, $options->segmentation, $version, $options->kanji);

        $eci = match ($options->eci) {
            EciMode::Always => true,
            EciMode::Never => false,
            EciMode::Auto => mb_check_encoding($data, 'UTF-8') && self::carriesNonAsciiBytes($segments),
        };

        if (! $eci) {
            return $segments;
        }

        foreach ($segments as $segment) {
            if ($segment->mode === Mode::Kanji) {
                $segments = Segmenter::segment($data, $options->segmentation, $version);

                break;
            }
        }

        return [Segment::eci(Segment::ECI_UTF8), ...$segments];
    }

    /**
     * Whether a byte segment holds a byte of 0x80 or above (its bit string has a leading 1 in
     * some octet).
     *
     * @param  list<Segment>  $segments
     */
    private static function carriesNonAsciiBytes(array $segments): bool
    {
        foreach ($segments as $segment) {
            if ($segment->mode !== Mode::Byte) {
                continue;
            }

            for ($i = 0, $length = strlen($segment->bits); $i < $length; $i += 8) {
                if ($segment->bits[$i] === '1') {
                    return true;
                }
            }
        }

        return false;
    }
}
