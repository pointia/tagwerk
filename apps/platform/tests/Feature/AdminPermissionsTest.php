<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Permission;
use App\Filament\Resources\Devices\Pages\EditDevice;
use App\Filament\Resources\People\Pages\EditPerson;
use App\Models\Device;
use App\Models\Household;
use App\Models\Person;
use App\Models\User;
use App\Support\HouseholdContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Parents set in the admin panel what a person or a wall device may change.
 */
final class AdminPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private Person $parent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();
        $this->parent = Person::factory()->create(['household_id' => $this->household->id]);
        $this->actingAs(User::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $this->parent->id,
        ]));

        Filament::setCurrentPanel('admin');
        app(HouseholdContext::class)->setId($this->household->id);
    }

    public function test_a_child_gets_its_own_permissions_and_back_to_the_defaults(): void
    {
        $child = Person::factory()->child()->create(['household_id' => $this->household->id]);

        Livewire::test(EditPerson::class, ['record' => $child->getRouteKey()])
            ->assertFormSet(['custom_permissions' => false])
            ->fillForm(['custom_permissions' => true])
            ->assertFormSet(['permissions' => array_map(
                fn (Permission $permission): string => $permission->value,
                Permission::defaultsFor($child->role),
            )])
            ->fillForm(['permissions' => [Permission::CalendarEditAll->value]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([Permission::CalendarEditAll->value], $child->fresh()->permissions);

        Livewire::test(EditPerson::class, ['record' => $child->getRouteKey()])
            ->assertFormSet(['custom_permissions' => true])
            ->fillForm(['custom_permissions' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($child->fresh()->permissions);
    }

    public function test_the_last_manager_cannot_lock_the_household_out(): void
    {
        Livewire::test(EditPerson::class, ['record' => $this->parent->getRouteKey()])
            ->fillForm(['custom_permissions' => true, 'permissions' => [Permission::ListsEdit->value]])
            ->call('save');

        $this->assertNull($this->parent->fresh()->permissions);

        // With a second parent account it is allowed.
        $partner = Person::factory()->create(['household_id' => $this->household->id]);
        User::factory()->create(['household_id' => $this->household->id, 'person_id' => $partner->id]);

        Livewire::test(EditPerson::class, ['record' => $this->parent->getRouteKey()])
            ->fillForm(['custom_permissions' => true, 'permissions' => [Permission::ListsEdit->value]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([Permission::ListsEdit->value], $this->parent->fresh()->permissions);
    }

    public function test_a_wall_device_gets_restricted(): void
    {
        $device = Device::factory()->create(['household_id' => $this->household->id]);

        Livewire::test(EditDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm(['custom_permissions' => true, 'permissions' => [Permission::ListsEdit->value]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([Permission::ListsEdit->value], $device->fresh()->permissions);
    }

    public function test_an_account_without_the_permission_stays_out_of_the_panel(): void
    {
        $this->parent->update(['permissions' => [Permission::ListsEdit->value]]);

        $this->assertFalse(User::query()->whereRelation('memberships', 'person_id', $this->parent->id)->firstOrFail()
            ->canAccessPanel(Filament::getPanel('admin')));
    }
}
