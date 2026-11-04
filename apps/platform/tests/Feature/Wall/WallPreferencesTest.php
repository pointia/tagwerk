<?php

declare(strict_types=1);

namespace Tests\Feature\Wall;

use App\Enums\Permission;
use App\Models\Device;
use App\Models\Household;
use App\Models\User;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Start area and default views — per device.
 */
final class WallPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private string $token;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();
        app(HouseholdContext::class)->setId((int) $this->household->id);

        $this->device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        $this->token = app(DevicePairingService::class)->redeem($this->device->pairing_code)['token'];
    }

    public function test_no_access_without_token(): void
    {
        $this->getJson('/api/preferences')->assertUnauthorized();
    }

    /** A freshly paired tablet starts on the home page, tasks as day view. */
    public function test_the_defaults_apply_without_anyone_setting_anything(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/preferences')
            ->assertOk()
            ->assertJsonPath('preferences.start_area', 'dashboard')
            ->assertJsonPath('preferences.task_view', 'day')
            ->assertJsonPath('preferences.calendar_view', 'three')
            ->assertJsonPath('preferences.school_band', 'off')
            ->assertJsonPath('preferences.colored_icons', 'off')
            ->assertJsonPath('preferences.colored_emoji', 'on')
            ->assertJsonPath('preferences.animations', 'on');
    }

    public function test_menu_icons_and_animations_are_set_per_tablet(): void
    {
        $this->withToken($this->token)
            ->putJson('/api/preferences', ['colored_icons' => 'on', 'animations' => 'off'])
            ->assertOk()
            ->assertJsonPath('preferences.colored_icons', 'on')
            ->assertJsonPath('preferences.animations', 'off');
    }

    /** Glass is opt-in per device: the household only picks the gradient. */
    public function test_the_glass_look_is_off_until_a_device_switches_it_on(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/preferences')
            ->assertJsonPath('preferences.look', 'classic');

        $this->withToken($this->token)
            ->putJson('/api/preferences', ['look' => 'glass'])
            ->assertOk()
            ->assertJsonPath('preferences.look', 'glass');

        $this->withToken($this->token)
            ->putJson('/api/preferences', ['look' => 'liquid'])
            ->assertUnprocessable();
    }

    /** Menu and task icons switch apart: emoji in the menu, grey tasks, or the other way round. */
    public function test_task_icons_turn_grey_apart_from_the_menu(): void
    {
        $this->withToken($this->token)
            ->putJson('/api/preferences', ['colored_icons' => 'on', 'colored_emoji' => 'off'])
            ->assertOk()
            ->assertJsonPath('preferences.colored_icons', 'on')
            ->assertJsonPath('preferences.colored_emoji', 'off');

        $this->withToken($this->token)
            ->putJson('/api/preferences', ['colored_emoji' => 'grey'])
            ->assertUnprocessable();
    }

    public function test_a_setting_stays_on_the_device(): void
    {
        $this->withToken($this->token)
            ->putJson('/api/preferences', ['start_area' => 'calendar'])
            ->assertOk()
            ->assertJsonPath('preferences.start_area', 'calendar');

        $this->assertSame('calendar', $this->device->fresh()?->preferences['start_area']);
    }

    /** A toggle sends only its own value — the rest must not fall back because of it. */
    public function test_a_second_setting_does_not_delete_the_first(): void
    {
        $this->withToken($this->token)->putJson('/api/preferences', ['start_area' => 'photos'])->assertOk();

        $this->withToken($this->token)
            ->putJson('/api/preferences', ['task_view' => 'list'])
            ->assertOk()
            ->assertJsonPath('preferences.start_area', 'photos')
            ->assertJsonPath('preferences.task_view', 'list');
    }

    public function test_an_unknown_value_is_rejected(): void
    {
        $this->withToken($this->token)
            ->putJson('/api/preferences', ['start_area' => 'spaceship'])
            ->assertStatus(422);
    }

    /**
     * An older wall might have stored a value that no longer exists. Then the
     * default applies instead of the wall starting on an area that does not
     * exist.
     */
    public function test_a_stale_stored_value_falls_back_to_the_default(): void
    {
        $this->device->update(['preferences' => ['start_area' => 'missing']]);

        $this->withToken($this->token)
            ->getJson('/api/preferences')
            ->assertOk()
            ->assertJsonPath('preferences.start_area', 'dashboard');
    }

    public function test_the_menu_is_sorted_and_hidden_per_device(): void
    {
        $this->unlock();

        $this->withToken($this->token)
            ->putJson('/api/preferences', ['area_order' => ['tasks', 'calendar'], 'hidden_areas' => ['photos']])
            ->assertOk()
            ->assertJsonPath('preferences.area_order', ['tasks', 'calendar'])
            ->assertJsonPath('preferences.hidden_areas', ['photos']);
    }

    public function test_the_menu_starts_unsorted_and_complete(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/preferences')
            ->assertJsonPath('preferences.area_order', [])
            ->assertJsonPath('preferences.hidden_areas', []);
    }

    public function test_an_unknown_area_in_the_menu_is_rejected(): void
    {
        $this->unlock();

        $this->withToken($this->token)
            ->putJson('/api/preferences', ['hidden_areas' => ['spaceship']])
            ->assertStatus(422);
    }

    /** Otherwise a child hides half the wall in passing. */
    public function test_the_menu_needs_the_parent_pin_on_the_wall(): void
    {
        $this->parentWithPin($this->household);

        $this->withToken($this->token)
            ->putJson('/api/preferences', ['hidden_areas' => ['photos']])
            ->assertStatus(423);

        $this->assertNull($this->device->fresh()?->preferences['hidden_areas'] ?? null);
    }

    /** The phone's own menu belongs to the account; no PIN there. */
    public function test_a_phone_sorts_its_menu_without_a_pin(): void
    {
        $user = User::factory()->create(['household_id' => $this->household->id]);

        $this->withToken($user->createToken('phone')->plainTextToken)
            ->putJson('/api/preferences', ['area_order' => ['calendar', 'dashboard'], 'hidden_areas' => ['meals']])
            ->assertOk()
            ->assertJsonPath('preferences.area_order', ['calendar', 'dashboard'])
            ->assertJsonPath('preferences.hidden_areas', ['meals']);
    }

    /** Every phone tab can be hidden, including the ones added later. */
    public function test_a_phone_hides_recipes_and_photos(): void
    {
        $user = User::factory()->create(['household_id' => $this->household->id]);

        $this->withToken($user->createToken('phone')->plainTextToken)
            ->putJson('/api/preferences', ['hidden_areas' => ['recipes', 'photos'], 'start_area' => 'recipes'])
            ->assertOk()
            ->assertJsonPath('preferences.hidden_areas', ['recipes', 'photos'])
            ->assertJsonPath('preferences.start_area', 'recipes');
    }

    /** A phone is narrow: one day fits, three are crowded. A tablet keeps three. */
    public function test_an_account_starts_the_calendar_on_one_day(): void
    {
        $user = User::factory()->create(['household_id' => $this->household->id]);

        $this->withToken($user->createToken('phone')->plainTextToken)
            ->getJson('/api/preferences')
            ->assertJsonPath('preferences.calendar_view', 'day');

        Auth::forgetGuards();

        $this->withToken($this->token)
            ->getJson('/api/preferences')
            ->assertJsonPath('preferences.calendar_view', 'three');
    }

    /** The narrow menu stays behind the burger until an account chooses the bar. */
    public function test_an_account_moves_its_menu_to_the_bottom(): void
    {
        $phone = User::factory()->create(['household_id' => $this->household->id])
            ->createToken('phone')->plainTextToken;

        $this->withToken($phone)
            ->getJson('/api/preferences')
            ->assertJsonPath('preferences.phone_menu', 'drawer');

        $this->withToken($phone)
            ->putJson('/api/preferences', ['phone_menu' => 'bottom'])
            ->assertOk()
            ->assertJsonPath('preferences.phone_menu', 'bottom');

        $this->withToken($phone)
            ->putJson('/api/preferences', ['phone_menu' => 'top'])
            ->assertUnprocessable();
    }

    public function test_the_list_is_a_calendar_view_everywhere(): void
    {
        $this->withToken($this->token)
            ->putJson('/api/preferences', ['calendar_view' => 'list'])
            ->assertOk()
            ->assertJsonPath('preferences.calendar_view', 'list');
    }

    /** Only a tablet rests; an account has nothing to set there. */
    public function test_an_account_cannot_set_the_resting_mode(): void
    {
        $user = User::factory()->create(['household_id' => $this->household->id]);

        $this->withToken($user->createToken('phone')->plainTextToken)
            ->putJson('/api/preferences', ['screensaver_idle' => '10'])
            ->assertUnprocessable();
    }

    public function test_a_stale_area_in_a_stored_menu_is_dropped(): void
    {
        $this->device->update(['preferences' => ['area_order' => ['missing', 'tasks']]]);

        $this->withToken($this->token)
            ->getJson('/api/preferences')
            ->assertJsonPath('preferences.area_order', ['tasks']);
    }

    /** Outline and plain by default, as the stars always looked. */
    public function test_stars_are_drawn_per_tablet(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/preferences')
            ->assertJsonPath('preferences.star_filled', 'off')
            ->assertJsonPath('preferences.star_color', 'off');

        $this->withToken($this->token)
            ->putJson('/api/preferences', ['star_filled' => 'on', 'star_color' => 'on'])
            ->assertOk()
            ->assertJsonPath('preferences.star_filled', 'on')
            ->assertJsonPath('preferences.star_color', 'on');
    }

    /** The last filters stay on the tablet — without the parent PIN, they only choose what is shown. */
    public function test_the_last_filters_are_remembered(): void
    {
        $this->parentWithPin($this->household);

        $this->withToken($this->token)
            ->putJson('/api/preferences', ['task_people' => [4, null], 'calendar_people' => [7]])
            ->assertOk();

        $this->withToken($this->token)
            ->getJson('/api/preferences')
            ->assertJsonPath('preferences.task_people', [4, null])
            ->assertJsonPath('preferences.calendar_people', [7]);
    }

    public function test_a_filter_holds_only_person_ids(): void
    {
        $this->withToken($this->token)
            ->putJson('/api/preferences', ['task_people' => ['everyone']])
            ->assertUnprocessable();
    }

    public function test_a_stale_value_in_a_stored_filter_is_dropped(): void
    {
        $this->device->update(['preferences' => ['task_people' => ['x', 3, 3]]]);

        $this->withToken($this->token)
            ->getJson('/api/preferences')
            ->assertJsonPath('preferences.task_people', [3]);
    }

    /** A device allowed to arrange the wall, as after entering the PIN. */
    private function unlock(): void
    {
        $this->device->update(['permissions' => [Permission::DashboardArrange->value]]);
    }

    public function test_the_options_are_included_for_the_ui(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/preferences')->assertOk();

        $this->assertContains('photos', (array) $response->json('options.start_area'));
        $this->assertSame(['day', 'list'], $response->json('options.task_view'));
        $this->assertSame(['hidden', 'shown'], $response->json('options.task_done'));
    }
}
