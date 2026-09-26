<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Encoder\Capacity;
use RoundlyConsulting\Qr\Encoder\FunctionPatterns;
use RoundlyConsulting\Qr\Encoder\Placement;

it('places alignment patterns per ISO Annex E', function (int $version, array $positions): void {
    expect(FunctionPatterns::alignmentPositions($version))->toBe($positions);
})->with([
    [1, []],
    [2, [6, 18]],
    [7, [6, 22, 38]],
    [14, [6, 26, 46, 66]],
    [22, [6, 26, 50, 74, 98]],
    [32, [6, 34, 60, 86, 112, 138]],
    [36, [6, 24, 50, 76, 102, 128, 154]],
    [40, [6, 30, 58, 86, 114, 142, 170]],
]);

it('leaves exactly the raw data modules free in every version', function (): void {
    foreach (range(1, 40) as $version) {
        $grid = FunctionPatterns::template($version);
        $free = substr_count(implode('', $grid->function), '0');

        expect($free)->toBe(Capacity::rawDataModules($version))
            ->and(Placement::coordinates($version))->toHaveCount($free);
    }
});

it('draws finders, timing and the dark module', function (): void {
    $grid = FunctionPatterns::template(1);

    expect($grid->rows[0])->toStartWith('1111111')
        ->and($grid->rows[1])->toStartWith('1000001')
        ->and($grid->rows[6])->toBe('111111101010101111111')
        ->and($grid->isDark(8, 13))->toBeTrue()
        ->and($grid->isFunction(8, 13))->toBeTrue()
        ->and($grid->isFunction(10, 10))->toBeFalse();
});

it('returns an independent copy of the memoised template', function (): void {
    $first = FunctionPatterns::template(3);
    $first->set(10, 10, true);

    expect(FunctionPatterns::template(3)->isDark(10, 10))->toBeFalse();
});
