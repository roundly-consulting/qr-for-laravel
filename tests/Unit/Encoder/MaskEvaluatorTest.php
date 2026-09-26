<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Encoder\MaskEvaluator;

it('scores runs of five or more same-coloured modules (N1)', function (): void {
    // "11111" costs 3, "0000000" costs 3 + 2; the line is not finder-like.
    expect(MaskEvaluator::linePenalty('111110000000101', 15))->toBe(8);
});

it('scores a finder-like pattern with light space on either side (N3)', function (): void {
    // 1:1:3:1:1 followed by four light modules; the leading light border counts too.
    expect(MaskEvaluator::linePenalty('10111010000', 11))->toBe(80);
});

it('scores finder-like patterns only on the side with four widths of light', function (): void {
    expect(MaskEvaluator::linePenalty('0101110101', 30))->toBe(40)
        ->and(MaskEvaluator::linePenalty('1010111010', 30))->toBe(40);
});

it('scores 2x2 blocks (N2)', function (): void {
    expect(MaskEvaluator::blockPenalty(['110', '110', '001']))->toBe(3)
        ->and(MaskEvaluator::blockPenalty(['000', '000', '000']))->toBe(12);
});

it('scores dark-module imbalance (N4)', function (): void {
    expect(MaskEvaluator::balancePenalty(['101', '010', '101']))->toBe(10)
        ->and(MaskEvaluator::balancePenalty(['1111', '1111', '1111', '1111']))->toBe(90)
        ->and(MaskEvaluator::balancePenalty(['1110', '0000', '0000', '0000']))->toBe(60);
});

it('transposes rows into columns', function (): void {
    expect(MaskEvaluator::columns(['10', '01']))->toBe(['10', '01'])
        ->and(MaskEvaluator::columns(['110', '001', '010']))->toBe(['100', '101', '010']);
});

it('sums the four rules', function (): void {
    $rows = ['1111', '1111', '1111', '1111'];

    expect(MaskEvaluator::penalty($rows))->toBe(0 + 9 * 3 + 90);
});
