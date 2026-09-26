<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Encoder\Capacity;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;

/**
 * Anchors computed from ISO/IEC 18004:2015 Table 9: total codewords, remainder bits and
 * data codewords per level.
 */
it('matches the capacity anchors', function (int $version, int $total, int $remainder, array $data): void {
    expect(Capacity::totalCodewords($version))->toBe($total)
        ->and(Capacity::remainderBits($version))->toBe($remainder)
        ->and(Capacity::size($version))->toBe($version * 4 + 17)
        ->and(array_map(static fn (ErrorCorrection $ecc): int => Capacity::dataCodewords($version, $ecc), ErrorCorrection::cases()))->toBe($data);
})->with([
    [1, 26, 0, [19, 16, 13, 9]],
    [2, 44, 7, [34, 28, 22, 16]],
    [5, 134, 7, [108, 86, 62, 46]],
    [6, 172, 7, [136, 108, 76, 60]],
    [7, 196, 0, [156, 124, 88, 66]],
    [10, 346, 0, [274, 216, 154, 122]],
    [13, 532, 0, [428, 334, 244, 180]],
    [14, 581, 3, [461, 365, 261, 197]],
    [26, 1706, 4, [1370, 1062, 754, 596]],
    [27, 1828, 4, [1468, 1128, 808, 628]],
    [40, 3706, 0, [2956, 2334, 1666, 1276]],
]);

it('exposes block structure and bit capacity', function (): void {
    expect(Capacity::numBlocks(40, ErrorCorrection::High))->toBe(81)
        ->and(Capacity::eccCodewordsPerBlock(40, ErrorCorrection::High))->toBe(30)
        ->and(Capacity::dataBits(13, ErrorCorrection::Medium))->toBe(334 * 8)
        ->and(Capacity::rawDataModules(1))->toBe(208);
});

it('keeps every block structure consistent with the total codeword count', function (): void {
    foreach (range(1, 40) as $version) {
        foreach (ErrorCorrection::cases() as $ecc) {
            $blocks = Capacity::numBlocks($version, $ecc);
            $shortLength = intdiv(Capacity::totalCodewords($version), $blocks);

            expect(Capacity::dataCodewords($version, $ecc))->toBeGreaterThan(0)
                ->and($shortLength)->toBeGreaterThan(Capacity::eccCodewordsPerBlock($version, $ecc));
        }
    }
});
