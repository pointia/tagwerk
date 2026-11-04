<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ThemePreference;
use App\Models\Device;
use App\Models\Household;
use App\Models\User;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Light and dark theme by time of day or position of the sun.
 *
 * The reference values for the sun do not come from the code but from the
 * tables for Vienna on 21 December 2026: sunrise 07:42, sunset 16:02. If the
 * service calculated in UTC instead of the household's time zone, this would
 * read 06:42 and 15:02.
 */
final class ThemeTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create(['timezone' => 'Europe/Vienna']);
        app(HouseholdContext::class)->setId((int) $this->household->id);

        $device = app(DevicePairingService::class)->createCode($this->household, 'Flur');
        $this->token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];
    }

    private function atTime(string $moment): void
    {
        $this->travelTo(CarbonImmutable::parse($moment, 'Europe/Vienna'));
    }

    /** @param array<string, mixed> $attributes */
    private function configure(array $attributes): void
    {
        $this->household->update($attributes);
    }

    public function test_no_theme_without_sign_in(): void
    {
        $this->getJson('/api/theme')->assertUnauthorized();
    }

    /** Every device of the household gets the same gradient for the glass look. */
    public function test_the_household_backdrop_comes_with_the_theme(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/theme')
            ->assertOk()
            ->assertJsonPath('backdrop', 'dawn');

        $this->configure(['backdrop' => 'lagoon']);

        $this->withToken($this->token)
            ->getJson('/api/theme')
            ->assertJsonPath('backdrop', 'lagoon');
    }

    /** A tablet may pick its own gradient; `household` goes back to the household's. */
    public function test_a_device_chooses_its_own_backdrop(): void
    {
        $this->configure(['backdrop' => 'lagoon']);

        $this->withToken($this->token)->putJson('/api/preferences', ['backdrop' => 'dusk'])->assertOk();
        $this->withToken($this->token)->getJson('/api/theme')->assertJsonPath('backdrop', 'dusk');

        $this->withToken($this->token)->putJson('/api/preferences', ['backdrop' => 'household'])->assertOk();
        $this->withToken($this->token)->getJson('/api/theme')->assertJsonPath('backdrop', 'lagoon');

        $this->withToken($this->token)->putJson('/api/preferences', ['backdrop' => 'neon'])->assertUnprocessable();
    }

    public function test_fixed_times_light_during_the_day(): void
    {
        $this->atTime('2026-09-15 12:00');

        $this->withToken($this->token)
            ->getJson('/api/theme')
            ->assertOk()
            ->assertJsonPath('preference', 'auto')
            ->assertJsonPath('mode', 'fixed')
            ->assertJsonPath('theme', 'light')
            ->assertJsonPath('next_change', '2026-09-15T20:00:00+02:00');
    }

    public function test_fixed_times_dark_in_the_evening_until_morning(): void
    {
        $this->atTime('2026-09-15 21:30');

        $this->withToken($this->token)
            ->getJson('/api/theme')
            ->assertJsonPath('theme', 'dark')
            ->assertJsonPath('next_change', '2026-09-16T07:00:00+02:00');
    }

    /** After midnight it is still the same night — not already the next day. */
    public function test_fixed_times_still_dark_early_in_the_morning(): void
    {
        $this->atTime('2026-09-15 05:00');

        $this->withToken($this->token)
            ->getJson('/api/theme')
            ->assertJsonPath('theme', 'dark')
            ->assertJsonPath('next_change', '2026-09-15T07:00:00+02:00');
    }

    public function test_custom_fixed_times(): void
    {
        $this->configure(['theme_light_at' => '06:30', 'theme_dark_at' => '19:15']);
        $this->atTime('2026-09-15 19:20');

        $this->withToken($this->token)
            ->getJson('/api/theme')
            ->assertJsonPath('theme', 'dark')
            ->assertJsonPath('next_change', '2026-09-16T06:30:00+02:00');
    }

    public function test_sun_mode_follows_dusk_and_dawn(): void
    {
        $this->configure(['theme_mode' => 'sun', 'latitude' => 48.2, 'longitude' => 16.37]);
        $this->atTime('2026-12-21 12:00');

        $response = $this->withToken($this->token)
            ->getJson('/api/theme')
            ->assertOk()
            ->assertJsonPath('mode', 'sun')
            ->assertJsonPath('theme', 'light');

        $this->assertSame('07:42', CarbonImmutable::parse($response->json('light_from'))->tz('Europe/Vienna')->format('H:i'));
        $this->assertSame('16:02', CarbonImmutable::parse($response->json('dark_from'))->tz('Europe/Vienna')->format('H:i'));
    }

    /** Both directions: light earlier, dark later. */
    public function test_offset_positive_and_negative(): void
    {
        $this->configure([
            'theme_mode' => 'sun',
            'latitude' => 48.2,
            'longitude' => 16.37,
            'sunrise_offset' => -15,
            'sunset_offset' => 30,
        ]);
        $this->atTime('2026-12-21 16:10');

        $response = $this->withToken($this->token)
            ->getJson('/api/theme')
            // 16:10 is after sunset, but before sunset + 30.
            ->assertJsonPath('theme', 'light');

        $this->assertSame('07:27', CarbonImmutable::parse($response->json('light_from'))->tz('Europe/Vienna')->format('H:i'));
        $this->assertSame('16:32', CarbonImmutable::parse($response->json('dark_from'))->tz('Europe/Vienna')->format('H:i'));
    }

    /** Sun mode chosen but no coordinates ever entered: fixed times rather than no switch at all. */
    public function test_without_coordinates_the_fixed_times_apply(): void
    {
        $this->configure(['theme_mode' => 'sun']);
        $this->atTime('2026-12-21 19:00');

        $response = $this->withToken($this->token)
            ->getJson('/api/theme')
            ->assertJsonPath('theme', 'light');

        $this->assertSame('20:00', CarbonImmutable::parse($response->json('dark_from'))->tz('Europe/Vienna')->format('H:i'));
    }

    public function test_permanent_dark_overrides_automatic(): void
    {
        $this->atTime('2026-09-15 12:00');

        $this->withToken($this->token)
            ->putJson('/api/theme', ['preference' => 'dark'])
            ->assertOk()
            ->assertJsonPath('preference', 'dark')
            ->assertJsonPath('theme', 'dark')
            // No scheduled switch anymore as long as nobody changes it.
            ->assertJsonPath('next_change', null);

        $this->assertSame(ThemePreference::Dark, Device::query()->first()?->theme_preference);
    }

    public function test_back_to_automatic(): void
    {
        $this->atTime('2026-09-15 12:00');

        $this->withToken($this->token)->putJson('/api/theme', ['preference' => 'dark'])->assertOk();

        $this->withToken($this->token)
            ->putJson('/api/theme', ['preference' => 'auto'])
            ->assertJsonPath('theme', 'light');
    }

    public function test_unknown_choice_is_rejected(): void
    {
        $this->withToken($this->token)
            ->putJson('/api/theme', ['preference' => 'purple'])
            ->assertStatus(422);
    }

    /** Whoever likes their phone dark does not darken the wall in the hallway with it. */
    public function test_phone_and_wall_choose_separately(): void
    {
        $this->atTime('2026-09-15 12:00');

        $user = User::factory()->create(['household_id' => $this->household->id]);
        $phone = $user->createToken('phone')->plainTextToken;

        $this->withToken($phone)
            ->putJson('/api/theme', ['preference' => 'dark'])
            ->assertOk();

        $this->assertSame(ThemePreference::Dark, $user->refresh()->theme_preference);
        $this->assertSame(ThemePreference::Auto, Device::query()->first()?->theme_preference);
    }
}
