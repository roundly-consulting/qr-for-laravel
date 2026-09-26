<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Qr\QrServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /** @return list<class-string<ServiceProvider>> */
    protected function packageProviders(): array
    {
        return [QrServiceProvider::class];
    }

    // No migrationSources() override: the package ships no migrations and never opens a
    // database connection.
}
