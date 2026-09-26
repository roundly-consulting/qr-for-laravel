<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr;

use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\Qr\Support\AboutSection;

final class QrServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('qr')
            ->hasConfigFile()
            ->hasTranslations()
            ->contributesToAbout(static fn (): array => AboutSection::payload());
    }
}
