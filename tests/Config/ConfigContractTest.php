<?php

declare(strict_types=1);

/**
 * The config contract, both directions: every key the code reads is shipped, and every
 * shipped leaf is read.
 *
 * `extraReadPrefixes` because ConfigGuard reads most keys through the toolkit validator
 * (`Config::using(...)->intBetween('qr.versions.min', ...)`), which the token scraper does
 * not recognise as a `config()` call. `AboutSection.php` is excluded from the reverse
 * direction: rendering a key is not reading it, so every key must be proven by ConfigGuard.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/qr.php')->toSatisfyConfigContract(__DIR__.'/../../src', [
        'extraReadPrefixes' => ['qr.'],
        'excludeFromReverse' => ['AboutSection.php'],
    ]);
});
