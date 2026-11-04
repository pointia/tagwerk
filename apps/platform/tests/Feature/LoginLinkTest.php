<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Http\Middleware\UserTokenFromCookie;
use App\Models\Household;
use App\Models\Person;
use App\Models\User;
use App\Notifications\LoginLink;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Signing in by mail: a link for the browser, a code for the installed app.
 */
final class LoginLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $household = Household::factory()->create();
        $this->user = User::factory()->create([
            'household_id' => $household->id,
            'person_id' => Person::factory()->create(['household_id' => $household->id])->id,
            'email' => 'kim@example.test',
        ]);
    }

    /** @return array{url: string, code: ?string, token: string} */
    private function mail(): array
    {
        $found = null;
        Notification::assertSentTo($this->user, LoginLink::class, function (LoginLink $notification) use (&$found): bool {
            $mail = $notification->toMail($this->user);
            parse_str((string) parse_url((string) $mail->actionUrl, PHP_URL_QUERY), $query);
            preg_match('/\*\*([A-Z0-9]{6})\*\*/', implode("\n", [...$mail->introLines, ...$mail->outroLines]), $code);
            $found = ['url' => (string) $mail->actionUrl, 'code' => $code[1] ?? null, 'token' => (string) ($query['login'] ?? '')];

            return true;
        });

        return $found;
    }

    private function requestForApp(): array
    {
        $this->postJson('/api/auth/link', ['email' => 'kim@example.test'])->assertStatus(202);

        return $this->mail();
    }

    private function redeem(array $secret): TestResponse
    {
        return $this->postJson('/api/auth/link/redeem', ['email' => 'kim@example.test', ...$secret]);
    }

    public function test_an_unknown_address_gets_the_same_answer_and_no_mail(): void
    {
        $this->postJson('/api/auth/link', ['email' => 'nobody@example.test'])->assertStatus(202);

        Notification::assertNothingSent();
    }

    public function test_the_code_signs_the_app_in_once(): void
    {
        $mail = $this->requestForApp();

        $this->assertStringStartsWith(url('/?'), $mail['url']);
        $this->assertNotNull($mail['code']);

        $this->redeem(['code' => strtolower($mail['code'])])
            ->assertOk()
            ->assertJsonPath('email', 'kim@example.test')
            ->assertCookie(UserTokenFromCookie::COOKIE);

        $this->redeem(['code' => $mail['code']])->assertUnprocessable();
    }

    public function test_the_link_signs_in_as_well(): void
    {
        $mail = $this->requestForApp();

        $this->redeem(['token' => $mail['token']])->assertOk();
    }

    public function test_five_wrong_codes_void_the_mail(): void
    {
        $mail = $this->requestForApp();
        // The request limit counts the mail request too; start afresh.
        $this->travel(61)->seconds();

        for ($i = 0; $i < 5; $i++) {
            $this->redeem(['code' => 'ZZZZZZ'])->assertUnprocessable();
        }

        // Past the request limit, so only the voided code can answer.
        $this->travel(61)->seconds();
        $this->redeem(['code' => $mail['code']])->assertUnprocessable();
    }

    public function test_the_link_expires(): void
    {
        $mail = $this->requestForApp();

        $this->travel(16)->minutes();

        $this->redeem(['code' => $mail['code']])->assertUnprocessable();
    }

    public function test_it_ends_an_open_invitation(): void
    {
        $this->user->forceFill(['email_verified_at' => null])->save();

        $this->redeem(['code' => $this->requestForApp()['code']])->assertOk();

        $this->assertNotNull($this->user->fresh()?->email_verified_at);
    }

    public function test_the_admin_panel_sends_a_link_without_code_that_signs_in(): void
    {
        Filament::setCurrentPanel('admin');

        $this->get('/admin/login')->assertSee('Stattdessen Anmeldelink per E-Mail');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'kim@example.test'])
            ->call('sendLoginLink')
            ->assertNotified('Falls es zu dieser Adresse ein Konto gibt, ist der Link unterwegs.');

        $mail = $this->mail();
        $this->assertNull($mail['code']);
        $this->assertStringStartsWith(url('/login-link?'), $mail['url']);

        $this->get($mail['url'])->assertRedirect(url('/admin'));
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_a_used_admin_link_leads_back_to_the_login(): void
    {
        $this->get('/login-link?email=kim%40example.test&login=forged')
            ->assertRedirect(Filament::getPanel('admin')->getLoginUrl());

        $this->assertGuest();
    }

    public function test_the_app_learns_that_mail_is_on(): void
    {
        $this->getJson('/api/auth/methods')->assertOk()->assertExactJson(['mail' => true]);
    }
}
