<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Tests\Support;

use RoundlyConsulting\Qr\DataTransferObjects\EncodeOptions;
use RoundlyConsulting\Qr\Encoder\Segment;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;

/**
 * Golden matrices from an independent ISO/IEC 18004 encoder, committed as static JSON.
 * Segments are explicit so a golden never depends on the segmentation strategy.
 */
final class GoldenFixtures
{
    /**
     * @return array<string, array{segments: list<Segment>, options: EncodeOptions, version: int, mask: int, rows: list<string>, data: string}>
     */
    public static function all(): array
    {
        $cases = [];

        foreach (glob(__DIR__.'/../Fixtures/golden/*.json') ?: [] as $file) {
            /** @var array{segments: list<array{mode: string, data: string}>, ecc: string, version: ?int, mask: int, boost: bool, expected: array{version: int, mask: int, rows: list<string>}} $fixture */
            $fixture = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

            $segments = array_map(static fn (array $segment): Segment => match ($segment['mode']) {
                'numeric' => Segment::numeric($segment['data']),
                'alphanumeric' => Segment::alphanumeric($segment['data']),
                default => Segment::bytes($segment['data']),
            }, $fixture['segments']);

            $cases[basename($file, '.json')] = [
                'segments' => $segments,
                'options' => new EncodeOptions(
                    errorCorrection: ErrorCorrection::from($fixture['ecc']),
                    minVersion: $fixture['version'] ?? 1,
                    maxVersion: $fixture['version'] ?? 40,
                    mask: $fixture['mask'] < 0 ? null : $fixture['mask'],
                    boostErrorCorrection: $fixture['boost'],
                ),
                'version' => $fixture['expected']['version'],
                'mask' => $fixture['expected']['mask'],
                'rows' => $fixture['expected']['rows'],
                'data' => implode('', array_column($fixture['segments'], 'data')),
            ];
        }

        return $cases;
    }
}
