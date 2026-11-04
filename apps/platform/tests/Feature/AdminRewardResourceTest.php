<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Rewards\Pages\CreateReward;
use App\Models\Household;
use App\Models\Reward;
use App\Models\User;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The admin page for rewards.
 *
 * As with tasks and lists, the form was relieved of `household_id`. Without
 * this test nobody would notice the field coming back — it would then offer
 * every household for selection.
 */
final class AdminRewardResourceTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(Household $household): void
    {
        $this->actingAs(User::factory()->create(['household_id' => $household->id]));
    }

    public function test_reward_list_shows_own(): void
    {
        $household = Household::factory()->create();
        $this->signIn($household);

        Reward::factory()->create([
            'household_id' => $household->id,
            'name' => 'Kinoabend',
        ]);

        $this->get('/admin/rewards')
            ->assertSuccessful()
            ->assertSee('Kinoabend');
    }

    public function test_reward_list_hides_foreign_households(): void
    {
        $this->signIn(Household::factory()->create());

        Reward::factory()->create([
            'household_id' => Household::factory()->create()->id,
            'name' => 'Kinoabend',
        ]);

        $this->get('/admin/rewards')
            ->assertSuccessful()
            ->assertDontSee('Kinoabend');
    }

    public function test_reward_form_loads(): void
    {
        $this->signIn(Household::factory()->create());

        $this->get('/admin/rewards/create')->assertSuccessful();
    }

    /**
     * Checking the page content does not work here: „Household" is in the
     * navigation bar anyway. What is meant is the form field.
     */
    public function test_form_offers_no_household_selection(): void
    {
        $household = Household::factory()->create();
        $this->signIn($household);

        // Livewire::test bypasses the middleware that otherwise fills the context.
        app(HouseholdContext::class)->setId($household->id);

        Livewire::test(CreateReward::class)
            ->assertFormFieldExists('cost')
            ->assertFormFieldDoesNotExist('household_id');
    }

    /**
     * The picker searches the shared catalog (its words come from there,
     * not from the page); any emoji still goes.
     */
    public function test_the_icon_comes_from_the_picker_or_is_typed(): void
    {
        $household = Household::factory()->create();
        $this->signIn($household);
        app(HouseholdContext::class)->setId($household->id);

        $this->get('/admin/rewards/create')->assertSee('data-icon-search', false)->assertSee('/emoji/catalog.json', false);

        foreach (['🎬', '🧙'] as $icon) {
            Livewire::test(CreateReward::class)
                ->fillForm(['name' => "Belohnung {$icon}", 'icon' => $icon, 'cost' => 10, 'active' => true])
                ->call('create')
                ->assertHasNoFormErrors();
        }

        $this->assertSame(['🎬', '🧙'], Reward::query()->orderBy('id')->pluck('icon')->all());

        Livewire::test(CreateReward::class)
            ->fillForm(['name' => 'Zu lang', 'icon' => str_repeat('x', 17), 'cost' => 10])
            ->call('create')
            ->assertHasFormErrors(['icon']);
    }
}
