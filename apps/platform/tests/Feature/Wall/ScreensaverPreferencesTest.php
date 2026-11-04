<?php

declare(strict_types=1);

namespace Tests\Feature\Wall;

use App\Enums\Feature;
use App\Filament\Resources\Devices\Pages\EditDevice;
use App\Models\Device;
use App\Models\Household;
use App\Models\User;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The resting wall: which areas it shows, after how long and how fast they
 * change — set at the wall or in the admin panel, stored on the device.
 */
final class ScreensaverPreferencesTest extends TestCase
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

        $pairing = app(DevicePairingService::class);
        $code = $pairing->createCode($this->household, 'Flur');
        $this->token = $pairing->redeem($code->pairing_code)['token'];
        $this->device = Device::query()->firstOrFail();
    }

    public function test_off_by_default_with_five_minutes_and_thirty_seconds(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/preferences')
            ->assertOk()
            ->assertJsonPath('preferences.screensaver_areas', [])
            ->assertJsonPath('preferences.screensaver_idle', '5')
            ->assertJsonPath('preferences.screensaver_interval', '30')
            ->assertJsonPath('preferences.screensaver_dim', '50')
            ->assertJsonPath('preferences.screensaver_dim_night', '70');
    }

    /** Day and night apart, in steps of five. */
    public function test_the_wall_sets_how_dark_it_rests_by_day_and_at_night(): void
    {
        $this->withToken($this->token)
            ->putJson('/api/preferences', ['screensaver_dim' => '35', 'screensaver_dim_night' => '85'])
            ->assertOk();

        $stored = $this->device->fresh()?->preferences ?? [];
        $this->assertSame('35', $stored['screensaver_dim']);
        $this->assertSame('85', $stored['screensaver_dim_night']);

        foreach (['screensaver_dim', 'screensaver_dim_night'] as $key) {
            foreach (['52', '95', '100'] as $value) {
                $this->withToken($this->token)
                    ->putJson('/api/preferences', [$key => $value])
                    ->assertUnprocessable();
            }
        }
    }

    public function test_the_wall_stores_its_carousel_in_order(): void
    {
        $this->withToken($this->token)
            ->putJson('/api/preferences', [
                'screensaver_areas' => ['photos', 'calendar', 'tasks'],
                'screensaver_idle' => '2',
                'screensaver_interval' => '15',
            ])
            ->assertOk();

        $this->assertSame(['photos', 'calendar', 'tasks'], $this->device->fresh()?->preferences['screensaver_areas']);
    }

    /** Lists are for working with; a resting wall does not show them. */
    public function test_areas_for_working_are_refused(): void
    {
        $this->withToken($this->token)
            ->putJson('/api/preferences', ['screensaver_areas' => ['lists']])
            ->assertUnprocessable();

        $this->withToken($this->token)
            ->putJson('/api/preferences', ['screensaver_idle' => '3'])
            ->assertUnprocessable();
    }

    public function test_the_admin_panel_sets_it_and_keeps_the_rest(): void
    {
        $this->device->update(['preferences' => ['calendar_view' => 'week']]);
        $this->household->update(['disabled_features' => [Feature::Points->value]]);

        $this->actingAs(User::factory()->create(['household_id' => $this->household->id]));
        Filament::setCurrentPanel('admin');

        Livewire::test(EditDevice::class, ['record' => $this->device->getRouteKey()])
            ->assertFormSet(['preferences.screensaver_idle' => '5'])
            ->assertFormFieldExists('preferences.screensaver_areas', fn ($field): bool => ! array_key_exists('points', $field->getOptions()) && ! array_key_exists('lists', $field->getOptions()))
            ->assertFormSet(['preferences.screensaver_dim' => 50, 'preferences.screensaver_dim_night' => 70])
            ->fillForm(['preferences.screensaver_areas' => ['calendar', 'photos'], 'preferences.screensaver_idle' => '10', 'preferences.screensaver_dim' => 45, 'preferences.screensaver_dim_night' => 85])
            ->call('save')
            ->assertHasNoFormErrors();

        $stored = $this->device->fresh()?->preferences ?? [];
        $this->assertSame(['calendar', 'photos'], $stored['screensaver_areas']);
        $this->assertSame('10', $stored['screensaver_idle']);
        $this->assertSame('45', $stored['screensaver_dim']);
        $this->assertSame('85', $stored['screensaver_dim_night']);
        $this->assertSame('week', $stored['calendar_view']);
    }
}
