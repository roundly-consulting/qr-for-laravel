<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Exceptions\InvalidColorException;
use RoundlyConsulting\Qr\ValueObjects\Color;

it('normalises allow-listed colours', function (string $input, string $svg): void {
    expect(Color::parse($input)->toSvg())->toBe($svg)
        ->and(Color::isValid($input))->toBeTrue();
})->with([
    ['#FFF', '#fff'],
    ['#abcd', '#abcd'],
    [' #00FF00 ', '#00ff00'],
    ['#11223344', '#11223344'],
    ['RebeccaPurple', 'rebeccapurple'],
    ['transparent', 'none'],
    ['NONE', 'none'],
    ['currentcolor', 'currentColor'],
    ['rgb(0, 128, 255)', 'rgb(0,128,255)'],
    ['rgba(0,0,0,0.5)', 'rgba(0,0,0,0.5)'],
    ['rgb(10%, 20%, 30%)', 'rgb(10%,20%,30%)'],
    ['rgba(1, 2, 3, 50%)', 'rgba(1,2,3,50%)'],
]);

it('rejects anything off the allow-list', function (string $input): void {
    expect(Color::isValid($input))->toBeFalse();

    Color::parse($input, 'foreground');
})->throws(InvalidColorException::class, 'foreground')->with([
    'red" onload="alert(1)',
    'url(#x)',
    'var(--brand)',
    'expression(alert(1))',
    '#ggg',
    '#12345',
    'rgb(1,2)',
    'hsl(0, 0%, 0%)',
    '',
    'blue;fill:red',
]);

it('never echoes the rejected value', function (): void {
    try {
        Color::parse('<script>');
    } catch (InvalidColorException $e) {
        expect($e->getMessage())->not->toContain('<script>')
            ->and($e->reason)->toBe('color')
            ->and($e->field)->toBe('color');

        return;
    }

    $this->fail('expected an exception');
});

it('compares and detects transparency', function (): void {
    expect(Color::parse('transparent')->isTransparent())->toBeTrue()
        ->and(Color::parse('#000')->isTransparent())->toBeFalse()
        ->and(Color::parse('#FFF')->equals(new Color('#fff')))->toBeTrue();
});
