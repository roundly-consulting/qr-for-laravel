<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Qr\QrServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

/**
 * Registers the qr provider alone — proves the boot contract (no money at boot).
 */
abstract class QrOnlyTestCase extends PackageTestCase
{
    /** @return list<class-string<ServiceProvider>> */
    protected function packageProviders(): array
    {
        return [QrServiceProvider::class];
    }
}
