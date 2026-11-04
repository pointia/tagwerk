<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\People\Pages\CreatePerson;
use App\Filament\Resources\People\Pages\EditPerson;
use App\Models\Household;
use App\Models\Person;
use App\Models\User;
use App\Support\HouseholdContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Saved means done: every form in the admin panel leads back to its list. */
final class AdminRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_leads_back_to_the_list(): void
    {
        $household = Household::factory()->create();
        $this->actingAs(User::factory()->create(['household_id' => $household->id]));
        Filament::setCurrentPanel('admin');
        app(HouseholdContext::class)->setId($household->id);

        $person = Person::factory()->create(['household_id' => $household->id]);

        Livewire::test(EditPerson::class, ['record' => $person->getRouteKey()])
            ->fillForm(['name' => 'Neu'])
            ->call('save')
            ->assertRedirect('/admin/people');

        Livewire::test(CreatePerson::class)
            ->fillForm(['name' => 'Oskar', 'color' => '#123456', 'role' => 'child'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin/people');
    }
}
