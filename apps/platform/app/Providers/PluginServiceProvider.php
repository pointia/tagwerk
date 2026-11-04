<?php

declare(strict_types=1);

namespace App\Providers;

use App\Integrations\Plugins\PluginLoader;
use App\Integrations\Plugins\PluginManifest;
use App\Support\PackTranslationLoader;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\ServiceProvider;

/**
 * Loads the installed plugins — first among the application's providers, so
 * everything after it sees what they offered.
 */
final class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PluginManifest::class);

        $this->app->make(PluginLoader::class)->load($this->app, $this->app->make(PluginManifest::class)->packages());

        // Language packs count for their own code only — see PackTranslationLoader.
        $this->app->extend('translation.loader', fn (Loader $loader, Application $app): Loader => new PackTranslationLoader($loader, $app));
    }
}
