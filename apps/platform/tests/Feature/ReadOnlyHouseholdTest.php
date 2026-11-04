<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\EnsureWritable;
use App\Models\Device;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Routes;
use Tests\TestCase;

/**
 * A read-only household reads everything and changes nothing — refused by
 * the server, not hidden by the app.
 */
final class ReadOnlyHouseholdTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private string $wall;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create(['read_only_since' => now()]);
        $this->wall = Device::factory()->create(['household_id' => $this->household->id])
            ->createToken('wall')->plainTextToken;
    }

    public function test_reading_works(): void
    {
        $this->withToken($this->wall)->getJson('/api/wall/agenda?from=2026-11-02&to=2026-11-08')->assertOk();
    }

    public function test_writing_is_refused_before_validation(): void
    {
        $response = $this->withToken($this->wall)
            ->postJson('/api/wall/tasks', [])
            ->assertStatus(402);

        $this->assertStringStartsWith('Dieser Haushalt ist schreibgeschützt.', (string) $response->json('message'));
    }

    public function test_writing_works_again_once_writable(): void
    {
        $this->household->forceFill(['read_only_since' => null])->save();

        $this->withToken($this->wall)->postJson('/api/wall/tasks', [])->assertUnprocessable();
    }

    public function test_the_device_keeps_its_own_settings(): void
    {
        $this->withToken($this->wall)
            ->putJson('/api/preferences', ['task_view' => 'list'])
            ->assertOk();
    }

    public function test_an_account_can_still_sign_out(): void
    {
        $user = User::factory()->create(['household_id' => $this->household->id]);
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();
    }

    public function test_only_writable_households_are_synced(): void
    {
        $writable = Household::factory()->create();

        $this->assertSame([$writable->id], Household::writable()->pluck('id')->all());
    }

    /**
     * Allowing a route while read-only is a decision, not an accident: this
     * list changes with it.
     */
    public function test_the_routes_allowed_while_read_only_are_known(): void
    {
        $allowed = collect(Routes::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => $route->getAction(EnsureWritable::SELF) === true)
            ->flatMap(fn (Route $route): array => array_map(
                fn (string $method): string => $method.' '.$route->uri(),
                array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']),
            ))
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'DELETE api/auth/account',
            'DELETE api/auth/household',
            'DELETE api/auth/invitations/{household}',
            'DELETE api/devices/me',
            'DELETE api/push/subscribe',
            'DELETE api/wall/dashboard/{widget}',
            'DELETE api/wall/pin',
            'PATCH api/wall/dashboard/{widget}',
            'POST api/auth/invitations/{household}',
            'POST api/auth/logout',
            'POST api/notices/dismiss',
            'POST api/notifications/read',
            'POST api/push/subscribe',
            'POST api/wall/dashboard',
            'POST api/wall/pin',
            'PUT api/auth/household',
            'PUT api/auth/locale',
            'PUT api/calendars/choice',
            'PUT api/preferences',
            'PUT api/theme',
        ], $allowed);
    }

    /**
     * A route that writes without the household middleware is not guarded:
     * only these, which come before any sign-in or from outside, may.
     */
    public function test_every_writing_api_route_carries_the_household(): void
    {
        $unguarded = collect(Routes::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/'))
            ->filter(fn (Route $route): bool => array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']) !== [])
            ->reject(fn (Route $route): bool => in_array('household', $route->gatherMiddleware(), true))
            ->map(fn (Route $route): string => $route->uri())
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'api/auth/invitation',
            'api/auth/link',
            'api/auth/link/redeem',
            'api/auth/login',
            'api/auth/password/forgot',
            'api/auth/password/reset',
            'api/auth/two-factor',
            'api/calendar/{provider}/webhook',
            'api/csp-report',
            'api/devices/pair',
        ], $unguarded);
    }
}
