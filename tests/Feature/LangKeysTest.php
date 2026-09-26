<?php

declare(strict_types=1);

/**
 * Every exception `$reason` key has a user-facing `qr::qr.errors.*` translation, and every
 * translation belongs to a reason some exception can carry.
 */
it('pairs every exception reason with an errors translation and vice versa', function (): void {
    $reasons = [];

    foreach (glob(__DIR__.'/../../src/Exceptions/*.php') ?: [] as $file) {
        $class = 'RoundlyConsulting\\Qr\\Exceptions\\'.basename($file, '.php');
        $reflection = new ReflectionClass($class);

        foreach ($reflection->getReflectionConstants() as $constant) {
            if (str_starts_with($constant->getName(), 'REASON_') && $constant->getDeclaringClass()->getName() === $class) {
                $reasons[] = $constant->getValue();
            }
        }
    }

    $translations = trans('qr::qr.errors');

    expect($reasons)->not->toBeEmpty()
        ->and($translations)->toBeArray();

    $keys = array_keys($translations);
    sort($keys);
    $reasons = array_values(array_unique($reasons));
    sort($reasons);

    expect($keys)->toBe($reasons);
});

it('ships a description for every payload type', function (): void {
    expect(array_keys(trans('qr::qr.descriptions')))->toBe([
        'text', 'url', 'email', 'phone', 'sms', 'wifi', 'vcard', 'geo', 'otpauth', 'epc', 'bysquare',
    ])->and(trans('qr::qr.title'))->toBe('QR code');
});
