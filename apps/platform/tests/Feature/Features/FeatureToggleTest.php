<?php

declare(strict_types=1);

namespace Tests\Feature\Features;

use App\Areas\AreaDefinition;
use App\Areas\Areas;
use App\Enums\Feature;
use App\Models\Device;
use App\Models\Household;
use App\Support\Preferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Disabled also means disabled for the API.
 *
 * If it were only a matter of the navigation bar, a tablet with a stale state
 * would keep writing, and whoever knows the URL would get through anyway.
 */
final class FeatureToggleTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();

        $device = Device::factory()->create(['household_id' => $this->household->id]);
        $this->token = $device->createToken('wall')->plainTextToken;
    }

    private function disable(Feature ...$features): void
    {
        $this->household->update([
            'disabled_features' => array_map(
                static fn (Feature $feature): string => $feature->value,
                $features,
            ),
        ]);
    }

    public function test_a_new_household_has_everything_enabled(): void
    {
        foreach (Feature::cases() as $feature) {
            $this->assertTrue($this->household->has($feature), $feature->value);
        }
    }

    public function test_disabled_area_is_also_closed_via_the_api(): void
    {
        $this->disable(Feature::Points);

        $this->withToken($this->token)
            ->getJson('/api/wall/points')
            ->assertNotFound();
    }

    public function test_the_other_areas_stay_open(): void
    {
        $this->disable(Feature::Points);

        $this->withToken($this->token)
            ->getJson('/api/wall/tasks')
            ->assertOk();
    }

    public function test_the_wall_learns_which_areas_apply(): void
    {
        $this->disable(Feature::Points, Feature::Meals);

        $response = $this->withToken($this->token)
            ->getJson('/api/wall/agenda?from=2026-09-14&to=2026-09-20')
            ->assertOk();

        $features = $response->json('household.features');

        $this->assertContains('calendar', $features);
        $this->assertNotContains('points', $features);
        $this->assertNotContains('meals', $features);
    }

    /**
     * The wall's navigation bar comes entirely from the server: a new area is
     * a case in the enum, not a second list in the frontend.
     */
    public function test_the_wall_gets_the_bar_in_fixed_order(): void
    {
        $this->disable(Feature::Points);

        $bar = $this->withToken($this->token)
            ->getJson('/api/wall/agenda?from=2026-09-14&to=2026-09-20')
            ->assertOk()
            ->json('household.areas');

        // Built-in areas first; installed plugin areas (vendor/name) follow them.
        $builtIn = array_values(array_filter(array_column($bar, 'mode'), static fn (string $mode): bool => ! str_contains($mode, '/')));
        $this->assertSame(['dashboard', 'calendar', 'tasks', 'lists', 'meals', 'recipes', 'timetable', 'photos'], $builtIn);
        $this->assertSame($builtIn, array_slice(array_column($bar, 'mode'), 0, count($builtIn)));
        $this->assertSame(['feature' => 'meals', 'mode' => 'meals', 'label' => 'Essen', 'icon' => 'meals'], $bar[4]);
    }

    /** Every area works as a start screen — and only those. */
    public function test_the_start_areas_are_the_areas(): void
    {
        $builtIn = array_map(static fn (Feature $feature): string => $feature->wallMode(), Feature::cases());
        $allowed = Preferences::allowed()['start_area'];

        $this->assertSame($builtIn, array_slice($allowed, 0, count($builtIn)));
        // What follows is a plugin area with a view, never one without.
        $this->assertSame(
            array_keys(array_filter(app(Areas::class)->all(), static fn (AreaDefinition $area): bool => $area->inMenu && str_contains($area->key, '/'))),
            array_slice($allowed, count($builtIn)),
        );
    }

    /**
     * Without a meal plan a recipe has no place where it is needed — the
     * dependency applies even when only the meal plan was deselected.
     */
    public function test_recipes_fall_with_the_meal_plan(): void
    {
        $this->disable(Feature::Meals);

        $this->assertFalse($this->household->has(Feature::Recipes));
        $this->assertNotContains('recipes', $this->household->features());
    }

    public function test_data_survives_disabling(): void
    {
        $this->disable(Feature::Lists);
        $this->household->update(['disabled_features' => []]);

        $this->withToken($this->token)
            ->getJson('/api/wall/lists')
            ->assertOk();
    }
}
