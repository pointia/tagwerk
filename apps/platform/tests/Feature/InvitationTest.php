<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Models\Household;
use App\Models\Person;
use App\Models\User;
use App\Notifications\Invitation;
use App\Notifications\PasswordChanged;
use App\Services\Invitations;
use App\Support\HouseholdContext;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * An invited account sets its own password through the link in the mail.
 */
final class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private User $invited;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->household = Household::factory()->create(['name' => 'Familie Muster']);
        app(HouseholdContext::class)->setId($this->household->id);

        $this->invited = User::factory()->unverified()->create([
            'household_id' => $this->household->id,
            'person_id' => Person::factory()->create(['household_id' => $this->household->id])->id,
            'email' => 'lena@example.test',
            'password' => Invitations::placeholderPassword(),
        ]);
    }

    private function invite(): string
    {
        app(Invitations::class)->send($this->invited);

        $token = null;
        Notification::assertSentTo($this->invited, Invitation::class, function (Invitation $notification) use (&$token): bool {
            $mail = $notification->toMail($this->invited);
            parse_str((string) parse_url($mail->actionUrl, PHP_URL_QUERY), $query);
            $token = $query['invite'] ?? null;

            return str_contains(implode(' ', $mail->introLines), 'Familie Muster');
        });

        return (string) $token;
    }

    private function accept(string $token, string $password = 'my-own-password'): TestResponse
    {
        return $this->postJson('/api/auth/invitation', [
            'token' => $token,
            'email' => 'lena@example.test',
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    public function test_accepting_sets_the_password_and_ends_the_invitation(): void
    {
        $this->accept($this->invite())->assertNoContent();

        $account = $this->invited->fresh();
        $this->assertTrue(Hash::check('my-own-password', (string) $account?->password));
        $this->assertNotNull($account?->email_verified_at);
        // The first real password is no news to its owner.
        Notification::assertNotSentTo($this->invited, PasswordChanged::class);

        $this->postJson('/api/auth/login', ['email' => 'lena@example.test', 'password' => 'my-own-password'])->assertOk();
    }

    public function test_a_resent_invitation_voids_the_old_link(): void
    {
        $old = $this->invite();
        Notification::fake();
        $new = $this->invite();

        $this->accept($old)->assertUnprocessable();
        $this->accept($new)->assertNoContent();
    }

    public function test_the_link_holds_for_a_week(): void
    {
        $token = $this->invite();

        $this->travel(6)->days();

        $this->accept($token)->assertNoContent();
    }

    public function test_a_reset_in_the_admin_panel_also_ends_the_invitation(): void
    {
        event(new PasswordReset($this->invited));

        $this->assertFalse(Invitations::isPending($this->invited->fresh()));
    }

    public function test_the_parent_sees_the_state_and_resends(): void
    {
        $parent = User::factory()->create(['household_id' => $this->household->id]);
        $this->actingAs($parent);
        Filament::setCurrentPanel('admin');

        $invited = $this->invited->memberships()->firstOrFail();
        $active = $parent->memberships()->firstOrFail();

        Livewire::test(ListAccounts::class)
            ->assertTableColumnStateSet('status', 'invited', $invited)
            ->assertTableColumnFormattedStateSet('status', 'Eingeladen', $invited)
            ->assertTableColumnFormattedStateSet('status', 'Aktiv', $active)
            ->assertTableActionHidden('resendInvitation', $active)
            ->callTableAction('resendInvitation', $invited);

        Notification::assertSentTo($this->invited, Invitation::class);
    }
}
