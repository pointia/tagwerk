<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeviceKind;
use App\Filament\Resources\Devices\Pages\ListDevices;
use App\Models\Device;
use App\Models\Household;
use App\Models\User;
use App\Support\HouseholdContext;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Without this action there would be no way to pair a second tablet other
 * than through the console.
 */
final class DeviceCodeActionTest extends TestCase
{
    use RefreshDatabase;

    private function device(DeviceKind $kind): Device
    {
        $household = Household::factory()->create();
        $this->actingAs(User::factory()->create(['household_id' => $household->id]));

        // Livewire::test bypasses the middleware that otherwise fills the
        // context. Without it the table would not find its own record.
        app(HouseholdContext::class)->setId($household->id);

        return Device::factory()->create([
            'household_id' => $household->id,
            'kind' => $kind,
        ]);
    }

    public function test_the_modal_shows_a_valid_code(): void
    {
        $device = $this->device(DeviceKind::Wall);

        $component = Livewire::test(ListDevices::class)
            ->mountAction(TestAction::make('pair')->table($device));

        $device->refresh();

        $this->assertNotNull($device->pairing_code);
        $this->assertTrue($device->pairing_expires_at->isFuture());

        // For typing and for scanning: both are in the modal.
        $content = (string) $component->instance()
            ->getMountedAction()
            ->getModalContent()
            ?->render();

        $this->assertStringContainsString((string) $device->pairing_code, $content);
        $this->assertStringContainsString('<svg', $content);
    }

    public function test_mobile_devices_get_no_code(): void
    {
        $device = $this->device(DeviceKind::Mobile);

        Livewire::test(ListDevices::class)
            ->assertActionHidden(TestAction::make('pair')->table($device));
    }
}
