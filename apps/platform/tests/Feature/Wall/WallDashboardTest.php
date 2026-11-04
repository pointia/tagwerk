<?php

declare(strict_types=1);

namespace Tests\Feature\Wall;

use App\Models\DashboardWidget;
use App\Models\Device;
use App\Models\Household;
use App\Models\User;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The home page made of tiles: place, move, remove.
 */
final class WallDashboardTest extends TestCase
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

        $device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        ['device' => $this->device, 'token' => $this->token] = app(DevicePairingService::class)->redeem($device->pairing_code);
    }

    private function unlock(): void
    {
        $this->parentWithPin($this->household);

        $this->withToken($this->token)->postJson('/api/wall/pin', ['pin' => '1234'])->assertOk();
    }

    private function tile(string $type, int $x, int $y, int $w = 3, int $h = 2): DashboardWidget
    {
        return DashboardWidget::factory()->create([
            'household_id' => $this->household->id,
            'device_id' => $this->device->id,
            'type' => $type,
            'x' => $x,
            'y' => $y,
            'w' => $w,
            'h' => $h,
        ]);
    }

    public function test_no_access_without_token(): void
    {
        $this->getJson('/api/wall/dashboard')->assertUnauthorized();
    }

    /** An empty grid would be the worst first impression. */
    public function test_the_first_call_places_a_layout(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/wall/dashboard')
            ->assertOk()
            ->assertJsonPath('columns', 12)
            ->assertJsonPath('widgets.0.type', 'calendar');

        $this->assertSame(5, DashboardWidget::query()->count());
    }

    public function test_the_defaults_leave_out_disabled_areas(): void
    {
        $this->household->update(['disabled_features' => ['calendar', 'points']]);

        $this->withToken($this->token)->getJson('/api/wall/dashboard')->assertOk();

        $placed = DashboardWidget::query()->pluck('type')->all();

        $this->assertNotContains('calendar', $placed);
        $this->assertNotContains('stars', $placed);
        $this->assertContains('weather', $placed);
    }

    /**
     * Disabled means invisible, not deleted: whoever re-enables the area half
     * a year later finds their layout from back then.
     */
    public function test_a_tile_of_a_disabled_area_only_disappears_from_the_display(): void
    {
        $this->tile('clock', 0, 0);
        $this->tile('stars', 3, 0);

        $this->household->update(['disabled_features' => ['points']]);

        $this->withToken($this->token)
            ->getJson('/api/wall/dashboard')
            ->assertOk()
            ->assertJsonCount(1, 'widgets')
            ->assertJsonPath('widgets.0.type', 'clock');

        $this->assertDatabaseHas('dashboard_widgets', ['type' => 'stars']);
    }

    public function test_the_selection_knows_no_widget_of_a_disabled_area(): void
    {
        $this->household->update(['disabled_features' => ['meals']]);

        $response = $this->withToken($this->token)->getJson('/api/wall/dashboard')->assertOk();

        $this->assertNotContains('meals', array_column((array) $response->json('available'), 'type'));
    }

    public function test_rearranging_requires_the_pin(): void
    {
        $this->parentWithPin($this->household);

        $this->withToken($this->token)
            ->postJson('/api/wall/dashboard', ['type' => 'clock', 'x' => 0, 'y' => 0])
            ->assertStatus(423)
            ->assertJsonPath('pin_required', true);
    }

    public function test_a_tile_is_added(): void
    {
        $this->unlock();

        $this->withToken($this->token)
            ->postJson('/api/wall/dashboard', ['type' => 'clock', 'x' => 0, 'y' => 0])
            ->assertCreated()
            ->assertJsonPath('widget.type', 'clock')
            ->assertJsonPath('widget.w', 3)
            ->assertJsonPath('widget.h', 2);
    }

    public function test_an_unknown_widget_is_rejected(): void
    {
        $this->unlock();

        $this->withToken($this->token)
            ->postJson('/api/wall/dashboard', ['type' => 'spaceship', 'x' => 0, 'y' => 0])
            ->assertStatus(422);
    }

    public function test_nothing_can_be_placed_where_something_already_is(): void
    {
        $this->unlock();
        $this->tile('clock', 0, 0, 3, 2);

        $this->withToken($this->token)
            ->postJson('/api/wall/dashboard', ['type' => 'weather', 'x' => 2, 'y' => 1])
            ->assertStatus(422)
            ->assertJsonPath('errors.x.0', 'Dort liegt schon eine Kachel.');
    }

    public function test_a_tile_must_not_reach_beyond_the_grid(): void
    {
        $this->unlock();

        $this->withToken($this->token)
            ->postJson('/api/wall/dashboard', ['type' => 'clock', 'x' => 10, 'y' => 0, 'w' => 4, 'h' => 2])
            ->assertStatus(422)
            ->assertJsonPath('errors.x.0', 'Die Kachel ragt über das Raster hinaus.');
    }

    public function test_moving_is_persisted(): void
    {
        $this->unlock();
        $tile = $this->tile('clock', 0, 0, 3, 2);

        $this->withToken($this->token)
            ->patchJson("/api/wall/dashboard/{$tile->id}", ['x' => 6, 'y' => 4, 'w' => 3, 'h' => 2])
            ->assertOk();

        $this->assertDatabaseHas('dashboard_widgets', ['id' => $tile->id, 'x' => 6, 'y' => 4]);
    }

    /** A tile does not overlap with itself. */
    public function test_a_tile_may_grow_in_place(): void
    {
        $this->unlock();
        $tile = $this->tile('clock', 0, 0, 3, 2);

        $this->withToken($this->token)
            ->patchJson("/api/wall/dashboard/{$tile->id}", ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 3])
            ->assertOk();
    }

    public function test_removing_takes_the_tile_out(): void
    {
        $this->unlock();
        $tile = $this->tile('clock', 0, 0);

        $this->withToken($this->token)
            ->deleteJson("/api/wall/dashboard/{$tile->id}")
            ->assertOk();

        $this->assertDatabaseMissing('dashboard_widgets', ['id' => $tile->id]);
    }

    public function test_a_foreign_tile_stays_untouched(): void
    {
        $this->unlock();

        $foreign = DashboardWidget::factory()->create([
            'household_id' => Household::factory()->create()->id,
            'type' => 'clock',
        ]);

        $this->withToken($this->token)
            ->deleteJson("/api/wall/dashboard/{$foreign->id}")
            ->assertNotFound();
    }

    /** Every tablet has its own dashboard: the kitchen's tiles are not the hallway's. */
    public function test_each_tablet_has_its_own_dashboard(): void
    {
        $this->tile('clock', 0, 0);

        $other = app(DevicePairingService::class)->createCode($this->household, 'Flur');
        $otherToken = app(DevicePairingService::class)->redeem($other->pairing_code)['token'];

        $this->withToken($otherToken)
            ->getJson('/api/wall/dashboard')
            ->assertOk()
            ->assertJsonPath('widgets.0.type', 'calendar');

        $this->assertSame(1, DashboardWidget::query()->where('device_id', $this->device->id)->count());
    }

    /** The household scope alone would let one tablet move another's tiles. */
    public function test_another_tablets_tile_is_not_there(): void
    {
        $this->unlock();

        $other = app(DevicePairingService::class)->createCode($this->household, 'Flur');
        ['device' => $otherDevice] = app(DevicePairingService::class)->redeem($other->pairing_code);

        $foreign = DashboardWidget::factory()->create([
            'household_id' => $this->household->id,
            'device_id' => $otherDevice->id,
            'type' => 'clock',
        ]);

        $this->withToken($this->token)
            ->patchJson("/api/wall/dashboard/{$foreign->id}", ['x' => 4, 'y' => 0, 'w' => 3, 'h' => 2])
            ->assertNotFound();
    }

    /** An account arranges its own dashboard without a permission or PIN. */
    public function test_an_account_arranges_its_own_dashboard(): void
    {
        $user = User::factory()->create(['household_id' => $this->household->id]);
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wall/dashboard')
            ->assertOk()
            ->assertJsonPath('widgets.0.type', 'calendar');

        $this->assertSame(5, DashboardWidget::query()->where('user_id', $user->id)->count());

        $this->withToken($token)
            ->postJson('/api/wall/dashboard', ['type' => 'clock', 'x' => 0, 'y' => 10])
            ->assertCreated()
            ->assertJsonPath('widget.user_id', $user->id);

        // The tablet's dashboard is untouched.
        $this->assertSame(0, DashboardWidget::query()->where('device_id', $this->device->id)->count());
    }

    /** A deleted tablet takes its tiles along. */
    public function test_a_deleted_tablet_takes_its_tiles_along(): void
    {
        $tile = $this->tile('clock', 0, 0);

        $this->device->delete();

        $this->assertDatabaseMissing('dashboard_widgets', ['id' => $tile->id]);
    }
}
