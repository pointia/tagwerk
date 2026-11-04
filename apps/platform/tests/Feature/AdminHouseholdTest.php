<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MealKind;
use App\Filament\Resources\Households\Pages\EditHousehold;
use App\Filament\Resources\Households\Schemas\HouseholdForm;
use App\Filament\Widgets\HouseholdOverview;
use App\Models\Household;
use App\Models\Task;
use App\Models\User;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Yasumi\Yasumi;

final class AdminHouseholdTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(Household $household): void
    {
        $this->actingAs(User::factory()->create(['household_id' => $household->id]));
    }

    public function test_foreign_households_stay_invisible(): void
    {
        $own = Household::factory()->create(['name' => 'Familie Berger']);
        $foreign = Household::factory()->create(['name' => 'Familie Nachbar']);
        $this->signIn($own);

        $this->get('/admin/households')
            ->assertSuccessful()
            ->assertSee('Familie Berger')
            ->assertDontSee('Familie Nachbar');

        $this->get("/admin/households/{$foreign->id}/edit")->assertNotFound();
        $this->get('/admin/households/create')->assertForbidden();
    }

    public function test_navigation_leads_directly_into_the_own_household(): void
    {
        $own = Household::factory()->create();
        $this->signIn($own);

        $this->get('/admin')
            ->assertSuccessful()
            ->assertSee("/admin/households/{$own->id}/edit", false);
    }

    public function test_every_holiday_region_is_known_to_yasumi(): void
    {
        foreach (HouseholdForm::holidayRegions() as $regions) {
            foreach (array_keys($regions) as $code) {
                $this->assertNotEmpty(Yasumi::createByISO3166_2($code, 2026)->getHolidays(), $code);
            }
        }
    }

    public function test_overview_counts_open_tasks_of_today(): void
    {
        $household = Household::factory()->create(['timezone' => 'Europe/Vienna']);
        $this->signIn($household);
        app(HouseholdContext::class)->setId($household->id);

        Task::factory()->count(2)->create([
            'household_id' => $household->id,
            'due_on' => now('Europe/Vienna')->toDateString(),
            'rrule' => null,
            'active' => true,
        ]);

        Livewire::test(HouseholdOverview::class)
            ->assertSee('Aufgaben offen')
            ->assertSee('von 2 heute')
            ->assertSee('Noch keines angelegt');
    }

    /*
     * The PIN is set in each parent's profile; the household only shows who
     * has one, so nobody wonders why the wall asks.
     */
    public function test_the_household_form_names_the_parents_with_a_pin(): void
    {
        $household = Household::factory()->create();
        $this->signIn($household);
        app(HouseholdContext::class)->setId($household->id);
        $parent = $this->parentWithPin($household);

        Livewire::test(EditHousehold::class, ['record' => $household->getRouteKey()])
            ->assertSee($parent->name)
            ->assertFormFieldDoesNotExist('new_pin');
    }

    public function test_the_day_bonus_is_set_beside_the_stars(): void
    {
        $household = Household::factory()->create();
        $this->signIn($household);
        app(HouseholdContext::class)->setId($household->id);

        Livewire::test(EditHousehold::class, ['record' => $household->getRouteKey()])
            ->fillForm(['day_bonus' => 2])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(2, $household->refresh()->day_bonus);

        Livewire::test(EditHousehold::class, ['record' => $household->getRouteKey()])
            ->fillForm(['day_bonus' => 50])
            ->call('save')
            ->assertHasFormErrors(['day_bonus']);

        // Without the stars there is nothing to add a bonus to.
        $household->update(['disabled_features' => ['points']]);

        Livewire::test(EditHousehold::class, ['record' => $household->getRouteKey()])
            ->assertFormFieldHidden('day_bonus');
    }

    public function test_the_household_chooses_when_it_cooks(): void
    {
        $household = Household::factory()->create();
        $this->signIn($household);
        app(HouseholdContext::class)->setId($household->id);

        // Nothing chosen yet means both, and the form shows it that way.
        Livewire::test(EditHousehold::class, ['record' => $household->getRouteKey()])
            ->assertFormSet(['meal_kinds' => ['lunch', 'dinner']])
            ->fillForm(['meal_kinds' => ['dinner']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([MealKind::Dinner], $household->refresh()->mealKinds());

        Livewire::test(EditHousehold::class, ['record' => $household->getRouteKey()])
            ->fillForm(['meal_kinds' => []])
            ->call('save')
            ->assertHasFormErrors(['meal_kinds']);
    }
}
