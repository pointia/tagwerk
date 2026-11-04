<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\ManagedHouseholds\Pages\ListManagedHouseholds;
use App\Models\Device;
use App\Models\Household;
use App\Models\Person;
use App\Models\SupportAccess;
use App\Models\User;
use App\Notifications\Invitation;
use App\Services\SupportAccessService;
use App\Support\HouseholdContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The operator manages all households in the admin panel and steps into one
 * to help — visibly, and with a trace the family can see.
 */
final class SupportAccessTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        $household = Household::factory()->create(['name' => 'Betreiber']);
        $user = User::factory()->create(['household_id' => $household->id]);
        $user->forceFill(['is_operator' => true])->save();

        return $user;
    }

    private function inPanel(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('admin');
        app(HouseholdContext::class)->setId((int) $user->memberships()->sole()->household_id);
    }

    public function test_a_parent_without_operator_right_neither_sees_nor_enters_other_households(): void
    {
        $parent = User::factory()->create(['household_id' => Household::factory()->create()->id]);
        $foreign = Household::factory()->create();

        $this->actingAs($parent)->get('/admin/operator/households')->assertForbidden();
        $this->actingAs($parent)
            ->post('/support/enter', ['household_id' => $foreign->id])
            ->assertForbidden();

        $this->assertSame(0, SupportAccess::query()->count());
    }

    public function test_the_operator_right_cannot_be_mass_assigned(): void
    {
        $user = User::create(['name' => 'X', 'email' => 'x@example.test', 'password' => 'long-enough-1', 'is_operator' => true]);

        $this->assertFalse($user->fresh()?->is_operator);
    }

    public function test_the_operator_sees_all_households_with_their_counts(): void
    {
        $household = Household::factory()->create(['name' => 'Familie Beispiel']);
        Person::factory()->count(3)->create(['household_id' => $household->id]);
        Device::factory()->create(['household_id' => $household->id]);

        $this->inPanel($this->operator());

        Livewire::test(ListManagedHouseholds::class)
            ->assertCanSeeTableRecords([$household])
            ->assertTableColumnStateSet('people_count', 3, $household)
            ->assertTableColumnStateSet('devices_count', 1, $household);
    }

    public function test_the_operator_creates_a_household_and_invites_its_first_parent(): void
    {
        Notification::fake();
        $this->inPanel($this->operator());

        Livewire::test(ListManagedHouseholds::class)
            ->callAction('create', [
                'household' => 'Familie Neu',
                'timezone' => 'Europe/Berlin',
                'parent' => 'Kim',
                'email' => 'kim@example.test',
            ])
            ->assertHasNoActionErrors();

        $household = Household::query()->where('name', 'Familie Neu')->firstOrFail();
        $parent = User::query()->where('email', 'kim@example.test')->firstOrFail();

        $this->assertSame('Europe/Berlin', $household->timezone);
        $this->assertSame($household->id, $parent->memberships()->sole()->household_id);
        $this->assertNull($parent->email_verified_at);
        Notification::assertSentTo($parent, Invitation::class);
        $this->assertTrue($parent->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($parent->is_operator);
    }

    public function test_a_suspended_household_loses_access_but_keeps_its_data(): void
    {
        $household = Household::factory()->create();
        $token = User::factory()->create(['household_id' => $household->id])->createToken('phone')->plainTextToken;

        $operator = $this->operator();
        $this->inPanel($operator);
        Livewire::test(ListManagedHouseholds::class)->callTableAction('suspend', $household);

        $this->assertNotNull($household->fresh()?->suspended_at);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/preferences')->assertForbidden();

        // The phone request above signed in its own user.
        $this->inPanel($operator);
        Livewire::test(ListManagedHouseholds::class)->callTableAction('resume', $household);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/preferences')->assertSuccessful();
    }

    public function test_the_operator_steps_into_a_household_and_back(): void
    {
        $operator = $this->operator();
        $family = Household::factory()->create(['name' => 'Familie Muster']);
        Person::factory()->create(['household_id' => $family->id, 'name' => 'Oskar Muster']);

        $this->actingAs($operator)
            ->post('/support/enter', ['household_id' => $family->id])
            ->assertRedirect();

        $this->get('/admin/people')
            ->assertSuccessful()
            ->assertSee('Oskar Muster')
            ->assertSee('Support-Zugang: <strong>Familie Muster</strong>', escape: false);

        $this->assertSame(1, SupportAccess::query()->where('household_id', $family->id)->count());

        $this->post('/support/leave')->assertRedirect();

        $this->get('/admin/people')
            ->assertSuccessful()
            ->assertDontSee('Oskar Muster')
            ->assertDontSee('Support-Zugang');
    }

    public function test_an_operator_without_household_sees_only_the_operation_area(): void
    {
        $operator = User::factory()->create(['household_id' => null]);
        $operator->forceFill(['is_operator' => true])->save();
        $family = Household::factory()->create(['name' => 'Familie Muster']);
        Person::factory()->create(['household_id' => $family->id, 'name' => 'Oskar Muster']);

        $this->actingAs($operator);

        $this->get('/admin')->assertSuccessful()->assertSee('Kein Haushalt');
        $this->get('/admin/operator/households')->assertSuccessful()->assertSee('Familie Muster');
        $this->get('/admin/people')->assertForbidden();

        $this->post('/support/enter', ['household_id' => $family->id]);
        $this->get('/admin/people')->assertSuccessful()->assertSee('Oskar Muster');

        $this->post('/support/leave');
        $this->get('/admin/people')->assertForbidden();
    }

    public function test_the_family_sees_the_last_support_access(): void
    {
        $family = Household::factory()->create();
        $parent = User::factory()->create(['household_id' => $family->id]);
        SupportAccess::query()->create(['user_id' => $this->operator()->id, 'household_id' => $family->id]);

        $this->actingAs($parent)
            ->get("/admin/households/{$family->id}/edit")
            ->assertSuccessful()
            ->assertSee('Letzter Support-Zugang')
            ->assertDontSee('Noch nie');
    }

    public function test_a_suspended_household_stays_reachable_for_support(): void
    {
        $family = Household::factory()->create();
        $family->forceFill(['suspended_at' => now()])->save();

        $this->actingAs($this->operator())
            ->withSession([SupportAccessService::SESSION_KEY => $family->id])
            ->get('/admin/people')
            ->assertSuccessful();
    }

    public function test_the_phone_ignores_the_support_access(): void
    {
        $operator = $this->operator();
        $family = Household::factory()->create();
        Person::factory()->create(['household_id' => $family->id, 'name' => 'Oskar Muster']);
        $token = $operator->createToken('phone')->plainTextToken;

        $this->actingAs($operator)->post('/support/enter', ['household_id' => $family->id]);
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/wall/agenda?from='.now()->toDateString().'&to='.now()->addDay()->toDateString())
            ->assertOk()
            ->assertDontSee('Oskar Muster');
    }
}
