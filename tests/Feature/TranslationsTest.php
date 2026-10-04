<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

/**
 * Every language ships the same keys and the same `:placeholders`, and the provider really
 * loads the Slovak files under the `qr` namespace.
 */
dataset('translation files', ['qr']);

it('ships the same keys in every language', function (string $file): void {
    $en = Arr::dot(require __DIR__.'/../../resources/lang/en/'.$file.'.php');
    $sk = Arr::dot(require __DIR__.'/../../resources/lang/sk/'.$file.'.php');

    expect($en)->not->toBeEmpty()
        ->and(array_keys($sk))->toBe(array_keys($en));
})->with('translation files');

it('keeps every placeholder in every language', function (string $file): void {
    $placeholders = static function (string $locale) use ($file): array {
        return array_map(static function (mixed $line): array {
            preg_match_all('/:([A-Za-z_]+)/', (string) $line, $matches);

            $names = array_values(array_unique($matches[1]));
            sort($names);

            return $names;
        }, Arr::dot(require __DIR__.'/../../resources/lang/'.$locale.'/'.$file.'.php'));
    };

    $en = $placeholders('en');

    expect($en)->not->toBeEmpty()
        ->and($placeholders('sk'))->toBe($en);
})->with('translation files');

it('loads slovak through the provider', function (): void {
    app()->setLocale('sk');

    expect(trans('qr::qr.title'))->toBe('QR kód')
        ->and(trans('qr::qr.errors.required', ['field' => 'ssid']))->toBe('Pole ssid je povinné.');

    app()->setLocale('en');

    expect(trans('qr::qr.title'))->toBe('QR code')
        ->and(trans('qr::qr.errors.required', ['field' => 'ssid']))->toBe('The ssid field is required.');
});
