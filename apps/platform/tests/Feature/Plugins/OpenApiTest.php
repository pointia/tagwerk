<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Routes;
use Tests\TestCase;

/**
 * The description lists the whole API: a route missing there is a route
 * nobody outside finds. Plugins' routes included.
 */
final class OpenApiTest extends TestCase
{
    public function test_every_api_route_is_described(): void
    {
        $paths = array_keys($this->getJson('/api/openapi.json')->assertOk()->json('paths'));

        $expected = collect(Routes::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/'))
            ->map(fn (Route $route): string => '/'.preg_replace('#^api/#', '', $route->uri()))
            ->reject(fn (string $path): bool => $path === '/openapi.json')
            ->map(fn (string $path): string => preg_replace('#\{(\w+)\?\}#', '{$1}', $path))
            ->unique()
            ->values();

        $this->assertSame([], $expected->diff($paths)->values()->all());
    }

    public function test_it_needs_no_sign_in(): void
    {
        $this->getJson('/api/openapi.json')
            ->assertOk()
            ->assertJsonPath('info.title', config('app.name'));
    }
}
