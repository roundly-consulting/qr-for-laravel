<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Compression\Lzma\LzmaDecoder;
use RoundlyConsulting\Qr\Compression\Lzma\LzmaEncoder;
use RoundlyConsulting\Qr\Compression\Lzma\LzmaProperties;

/**
 * Interoperability with independent LZMA implementations, proven by static fixtures:
 *
 *  - `<case>.external.lzma` was produced by an independent encoder (13-byte header, size
 *    unknown, end marker); ours must decode it to `<case>.input.bin`;
 *  - `<case>.ours.lzma` is this encoder's stream (13-byte header, size unknown) that was
 *    decoded by an independent decoder into `<case>.ours.decoded.bin`. Our encoder must
 *    still produce exactly that stream — any change fails here and requires re-verifying
 *    and refreshing the fixtures.
 */
function interopCases(): array
{
    /** @var list<array{case: string, length: int, lc: int, lp: int, pb: int, dictionary: int}> $manifest */
    $manifest = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/lzma/interop/manifest.json'), true, flags: JSON_THROW_ON_ERROR);

    return $manifest;
}

function interopFile(string $name): string
{
    return (string) file_get_contents(__DIR__.'/../../Fixtures/lzma/interop/'.$name);
}

it('pins the interop case count', function (): void {
    expect(interopCases())->toHaveCount(8)
        ->and(glob(__DIR__.'/../../Fixtures/lzma/interop/*.external.lzma'))->toHaveCount(8)
        ->and(glob(__DIR__.'/../../Fixtures/lzma/interop/*.ours.lzma'))->toHaveCount(8);
});

it('decodes streams from an independent encoder', function (array $case): void {
    $stream = interopFile($case['case'].'.external.lzma');
    $input = interopFile($case['case'].'.input.bin');
    $properties = new LzmaProperties($case['lc'], $case['lp'], $case['pb'], $case['dictionary']);

    expect(ord($stream[0]))->toBe(0x5D)
        ->and(unpack('V', substr($stream, 1, 4))[1])->toBe(1 << 17)
        ->and(strlen($input))->toBe($case['length'])
        ->and(LzmaDecoder::decode(substr($stream, 13), $case['length'], $properties))->toBe($input);
})->with(fn (): array => array_map(static fn (array $case): array => [$case], interopCases()));

it('produces the streams an independent decoder verified', function (array $case): void {
    $input = interopFile($case['case'].'.input.bin');
    $ours = interopFile($case['case'].'.ours.lzma');

    expect(substr($ours, 0, 13))->toBe((new LzmaProperties)->header())
        ->and(LzmaEncoder::encode($input))->toBe(substr($ours, 13))
        ->and(interopFile($case['case'].'.ours.decoded.bin'))->toBe($input);
})->with(fn (): array => array_map(static fn (array $case): array => [$case], interopCases()));
