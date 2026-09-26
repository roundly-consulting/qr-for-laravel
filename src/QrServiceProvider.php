<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\Qr\Commands\MakeQrCommand;
use RoundlyConsulting\Qr\Contracts\QrFactory;
use RoundlyConsulting\Qr\Encoder\Encoder;
use RoundlyConsulting\Qr\Support\AboutSection;
use RoundlyConsulting\Qr\Support\ConfigGuard;
use RoundlyConsulting\Qr\Support\MatrixMemo;
use RoundlyConsulting\Qr\Support\SvgCache;
use RoundlyConsulting\Qr\Svg\SvgRenderer;
use RoundlyConsulting\Qr\View\Components\QrCode;

final class QrServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('qr')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasCommands([MakeQrCommand::class])
            ->contributesToAbout(static fn (): array => AboutSection::payload());
    }

    public function register(): void
    {
        parent::register();

        // Nothing here resolves configuration or another package's services eagerly: the
        // bindings are lazy, so booting the provider never touches money, cache or config.
        $this->app->singleton(Encoder::class);
        $this->app->singleton(SvgRenderer::class);
        $this->app->singleton(MatrixMemo::class, static fn (): MatrixMemo => new MatrixMemo(ConfigGuard::memoEntries()));
        $this->app->singleton(SvgCache::class, static fn (Application $app): SvgCache => new SvgCache($app->make(CacheFactory::class)));
        $this->app->singleton(QrFactory::class, static fn (Application $app): QrManager => new QrManager(
            $app->make(Encoder::class),
            $app->make(SvgRenderer::class),
            $app->make(MatrixMemo::class),
            $app->make(SvgCache::class),
            $app->make('translator'),
        ));
        $this->app->alias(QrFactory::class, QrManager::class);
    }

    public function boot(): void
    {
        parent::boot();

        $component = ConfigGuard::bladeComponent();

        if ($component !== null) {
            Blade::component($component, QrCode::class);
        }
    }
}
