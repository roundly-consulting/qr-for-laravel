<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Encoder\FunctionPatterns;
use RoundlyConsulting\Qr\Encoder\Masks;

it('implements the eight ISO mask conditions', function (int $mask, Closure $condition): void {
    foreach (range(0, 20) as $y) {
        foreach (range(0, 20) as $x) {
            expect(Masks::isFlipped($mask, $x, $y))->toBe($condition($y, $x));
        }
    }
})->with([
    [0, fn (int $i, int $j): bool => ($i + $j) % 2 === 0],
    [1, fn (int $i, int $j): bool => $i % 2 === 0],
    [2, fn (int $i, int $j): bool => $j % 3 === 0],
    [3, fn (int $i, int $j): bool => ($i + $j) % 3 === 0],
    [4, fn (int $i, int $j): bool => (intdiv($i, 2) + intdiv($j, 3)) % 2 === 0],
    [5, fn (int $i, int $j): bool => ($i * $j) % 2 + ($i * $j) % 3 === 0],
    [6, fn (int $i, int $j): bool => (($i * $j) % 2 + ($i * $j) % 3) % 2 === 0],
    [7, fn (int $i, int $j): bool => (($i + $j) % 2 + ($i * $j) % 3) % 2 === 0],
]);

it('flips only data modules and undoes itself', function (): void {
    $grid = FunctionPatterns::template(2);
    $original = $grid->rows;

    Masks::apply($grid, 1);

    expect($grid->rows)->not->toBe($original)
        ->and($grid->rows[6])->toBe($original[6])
        ->and($grid->isDark(8, 25 - 8))->toBeTrue();

    Masks::apply($grid, 1);

    expect($grid->rows)->toBe($original);
});
