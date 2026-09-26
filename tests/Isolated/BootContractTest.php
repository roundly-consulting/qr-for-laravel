<?php

declare(strict_types=1);

use RoundlyConsulting\Money\MoneyServiceProvider;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Facades\Qr;

/**
 * Only QrServiceProvider is registered here: text, URL and otpauth codes must render
 * without money-for-laravel's provider, so hosts that never render payments (and the
 * authentication package's test suite) need not register it.
 */
it('renders non-payment codes without the money provider', function (): void {
    expect(app()->getProviders(MoneyServiceProvider::class))->toBe([])
        ->and(Qr::otpauth('otpauth://totp/Acme:user?secret=JBSWY3DP')->size(240)->svg()->sensitivity())->toBe(Sensitivity::Secret)
        ->and(Qr::url('https://example.com')->svg()->toString())->toStartWith('<svg ');
});
