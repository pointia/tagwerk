<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PersonRole;
use App\Filament\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Resources\Accounts\Pages\EditAccount;
use App\Models\Household;
use App\Models\Membership;
use App\Models\Person;
use App\Models\User;
use App\Notifications\HouseholdInvitation;
use App\Notifications\Invitation;
use App\Support\HouseholdContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Parents create the logins of their household in the admin panel.
 */
final class AdminAccountsTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();
        $this->parent = User::factory()->create(['household_id' => $this->household->id]);
        $this->actingAs($this->parent);

        Filament::setCurrentPanel('admin');
        // Livewire::test bypasses the middleware that would otherwise fill the context.
        app(HouseholdContext::class)->setId($this->household->id);
    }

    /** @param  array<string, mixed>  $attributes */
    private function person(array $attributes = []): Person
    {
        return Person::factory()->create(['household_id' => $this->household->id, ...$attributes]);
    }

    public function test_a_foreign_person_cannot_be_chosen(): void
    {
        $foreign = Person::factory()->create();

        Livewire::test(CreateAccount::class)
            ->fillForm(['person_id' => $foreign->id, 'email' => 'x@example.test'])
            ->call('create')
            ->assertHasFormErrors(['person_id']);
    }

    public function test_a_parent_invites_an_account_for_a_child(): void
    {
        Notification::fake();
        $child = $this->person(['name' => 'Lena', 'role' => PersonRole::Child]);

        Livewire::test(CreateAccount::class)
            ->assertFormFieldIsHidden('password')
            ->fillForm(['person_id' => $child->id, 'email' => 'lena@example.test'])
            ->call('create')
            ->assertHasNoFormErrors();

        $account = User::query()->where('email', 'lena@example.test')->firstOrFail();
        $this->assertSame($this->household->id, $account->memberships()->sole()->household_id);
        $this->assertSame('Lena', $account->name);
        $this->assertNull($account->email_verified_at);
        $this->assertFalse($account->canAccessPanel(Filament::getPanel('admin')));
        Notification::assertSentTo($account, Invitation::class);
    }

    public function test_a_person_has_at_most_one_account(): void
    {
        $person = $this->person();
        User::factory()->create(['household_id' => $this->household->id, 'person_id' => $person->id]);

        Livewire::test(CreateAccount::class)
            ->fillForm(['person_id' => $person->id, 'email' => 'second@example.test'])
            ->call('create')
            ->assertHasFormErrors(['person_id' => 'unique']);
    }

    public function test_the_list_shows_no_foreign_accounts(): void
    {
        $foreign = Household::factory()->create();
        User::factory()->create(['household_id' => $foreign->id, 'email' => 'foreign@example.test']);

        $this->get('/admin/accounts')
            ->assertSuccessful()
            ->assertDontSee('foreign@example.test');
    }

    public function test_a_foreign_account_cannot_be_opened(): void
    {
        $foreign = User::factory()->create(['household_id' => Household::factory()->create()->id]);

        $membership = $foreign->memberships()->firstOrFail();

        $this->get("/admin/accounts/{$membership->id}/edit")->assertNotFound();
    }

    public function test_an_empty_password_keeps_the_old_one_and_the_phone_signed_in(): void
    {
        $person = $this->person();
        $account = User::factory()->create(['household_id' => $this->household->id, 'person_id' => $person->id]);
        $account->createToken('phone');

        Livewire::test(EditAccount::class, ['record' => $this->membership($account)->getRouteKey()])
            ->fillForm(['email' => 'renamed@example.test', 'password' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('password', (string) $account->fresh()?->password));
        $this->assertSame(1, $account->tokens()->count());
    }

    /** Otherwise one family could take over grandma's sign-in in the other. */
    public function test_a_shared_account_keeps_its_address_and_password(): void
    {
        $person = $this->person();
        $account = User::factory()->create(['household_id' => $this->household->id, 'person_id' => $person->id, 'email' => 'oma@example.test']);
        $other = Household::factory()->create();
        $account->memberships()->create(['household_id' => $other->id, 'accepted_at' => now()]);

        Livewire::test(EditAccount::class, ['record' => $this->membership($account)->getRouteKey()])
            ->assertFormFieldIsDisabled('email')
            ->assertFormFieldIsHidden('password')
            ->fillForm(['email' => 'taken@example.test'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('oma@example.test', $account->fresh()?->email);
    }

    public function test_removing_a_shared_account_keeps_it_for_the_other_household(): void
    {
        $person = $this->person();
        $account = User::factory()->create(['household_id' => $this->household->id, 'person_id' => $person->id]);
        $other = Household::factory()->create();
        $account->memberships()->create(['household_id' => $other->id, 'accepted_at' => now()]);

        Livewire::test(EditAccount::class, ['record' => $this->membership($account)->getRouteKey()])
            ->callAction('delete');

        $this->assertNotNull($account->fresh());
        $this->assertSame([$other->id], $account->memberships()->pluck('household_id')->all());
    }

    public function test_a_known_address_is_invited_without_a_second_account(): void
    {
        Notification::fake();
        $known = User::factory()->create(['household_id' => Household::factory()->create()->id, 'email' => 'oma@example.test']);
        $person = $this->person(['name' => 'Oma', 'role' => PersonRole::Guest]);

        Livewire::test(CreateAccount::class)
            ->fillForm(['person_id' => $person->id, 'email' => 'OMA@example.test'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, User::query()->whereRaw('lower(email) = ?', ['oma@example.test'])->count());
        $this->assertNull($known->memberships()->where('household_id', $this->household->id)->value('accepted_at'));
        Notification::assertSentTo($known, HouseholdInvitation::class);
    }

    private function membership(User $account): Membership
    {
        return $account->memberships()->where('household_id', $this->household->id)->firstOrFail();
    }

    public function test_a_new_password_signs_out_every_phone(): void
    {
        $person = $this->person();
        $account = User::factory()->create(['household_id' => $this->household->id, 'person_id' => $person->id]);
        $account->createToken('phone');

        Livewire::test(EditAccount::class, ['record' => $this->membership($account)->getRouteKey()])
            ->fillForm(['password' => 'a-brand-new-one'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, $account->tokens()->count());
    }
}
