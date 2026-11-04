<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Providers\Filament\AdminPanelProvider;
use Filament\Panel;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Outside the console, registering resources asks the panel's id for
 * Filament's component cache. A plugin panel configured before `id()` broke
 * every admin page with a 500 — while PHPUnit, running in the console,
 * stayed green.
 */
final class PanelPluginsTest extends TestCase
{
    public function test_plugin_resources_register_like_in_a_web_request(): void
    {
        $console = new ReflectionProperty($this->app, 'isRunningInConsole');
        $console->setValue($this->app, false);

        try {
            $panel = (new AdminPanelProvider($this->app))->panel(Panel::make());

            $this->assertSame('admin', $panel->getId());
        } finally {
            $console->setValue($this->app, true);
        }
    }
}
