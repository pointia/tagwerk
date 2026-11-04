<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Enums\DeviceKind;
use App\Enums\PersonRole;
use App\Http\Middleware\DeviceTokenFromCookie;
use App\Http\Middleware\UserTokenFromCookie;
use App\Jobs\ConfirmPushJob;
use App\Models\Device;
use App\Models\Household;
use App\Models\Person;
use App\Models\Task;
use App\Models\User;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * The phone PWA signs in personally, not as a device.
 *
 * The second part of these tests is the more important one: the same
 * endpoints the wall uses with its device token must answer the same way
 * with a personal sign-in. Otherwise the PWA would need a second API that
 * has to grow alongside.
 */
final class MobileAccessTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create(['timezone' => 'Europe/Vienna']);
        app(HouseholdContext::class)->setId((int) $this->household->id);
    }

    private function user(?int $householdId = null): User
    {
        return User::factory()->create([
            'email' => 'alex@example.test',
            'password' => Hash::make('geheim123'),
            'household_id' => $householdId ?? $this->household->id,
        ]);
    }

    private function signIn(): string
    {
        $this->user();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'alex@example.test',
            'password' => 'geheim123',
        ])->assertOk();

        return $response->getCookie(UserTokenFromCookie::COOKIE, false)->getValue();
    }

    public function test_sign_in_sets_the_token_as_cookie(): void
    {
        $this->user();

        $this->postJson('/api/auth/login', [
            'email' => 'alex@example.test',
            'password' => 'geheim123',
        ])
            ->assertOk()
            ->assertJsonPath('email', 'alex@example.test')
            ->assertCookie(UserTokenFromCookie::COOKIE);
    }

    /** The token must not be readable by JavaScript. */
    public function test_the_cookie_is_httponly(): void
    {
        $this->user();

        $cookie = $this->postJson('/api/auth/login', [
            'email' => 'alex@example.test',
            'password' => 'geheim123',
        ])->getCookie(UserTokenFromCookie::COOKIE, false);

        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->user();

        $this->postJson('/api/auth/login', [
            'email' => 'alex@example.test',
            'password' => 'daneben',
        ])->assertStatus(422);
    }

    public function test_unknown_address_is_rejected(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'niemand@example.test',
            'password' => 'geheim123',
        ])->assertStatus(422);
    }

    public function test_user_without_household_cannot_get_in(): void
    {
        User::factory()->create([
            'email' => 'heimatlos@example.test',
            'password' => Hash::make('geheim123'),
            'household_id' => null,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'heimatlos@example.test',
            'password' => 'geheim123',
        ])->assertStatus(422);
    }

    public function test_me_returns_the_signed_in_user(): void
    {
        $token = $this->signIn();

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('email', 'alex@example.test');
    }

    public function test_sign_out_invalidates_the_token(): void
    {
        $token = $this->signIn();

        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

        // The actual proof: the token is gone.
        $this->assertSame(0, PersonalAccessToken::query()->count());

        /*
         * Without this the next request would stay signed in: Laravel keeps
         * the resolved user per guard in the container, and a test runs
         * several requests in the same container. In production every request
         * starts fresh, so this intermediate state does not exist there.
         */
        Auth::forgetGuards();

        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    /** The core: the wall endpoints also accept the personal sign-in. */
    public function test_wall_endpoints_also_answer_personal_sign_in(): void
    {
        $token = $this->signIn();

        $this->withToken($token)->getJson('/api/wall/points')->assertOk();
        $this->withToken($token)->getJson('/api/wall/lists')->assertOk();
        $this->withToken($token)->getJson('/api/wall/tasks')->assertOk();
        $this->withToken($token)->getJson('/api/wall/meals')->assertOk();
    }

    /**
     * On the wall the PIN replaces the missing sign-in. On the phone someone
     * is signed in and the action carries their name — there it would be a
     * second secret number without any gain.
     */
    public function test_signed_in_person_creates_a_task_without_pin(): void
    {
        $this->parentWithPin($this->household);

        $token = $this->signIn();

        $this->withToken($token)
            ->postJson('/api/wall/tasks', [
                'title' => 'Zähne putzen',
                'icon' => '🦷',
                'points' => 2,
                'person_id' => null,
                'date' => '2026-09-21',
                'weekdays' => ['MO'],
            ])
            ->assertCreated();

        $this->assertSame('FREQ=WEEKLY;BYDAY=MO', Task::query()->value('rrule'));
    }

    /**
     * A child with their own account is signed in, but not a parent. The PIN
     * is skipped on the phone because the sign-in replaces it — that only
     * applies to adults.
     */
    public function test_a_child_may_not_perform_a_parent_action_on_the_phone(): void
    {
        $child = Person::factory()->create([
            'household_id' => $this->household->id,
            'role' => PersonRole::Child,
        ]);

        $this->user()->memberships()->update(['person_id' => $child->id]);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'alex@example.test',
            'password' => 'geheim123',
        ])->getCookie(UserTokenFromCookie::COOKIE, false)->getValue();

        $this->withToken($token)
            ->postJson('/api/wall/tasks', [
                'title' => 'Zähne putzen',
                'points' => 99,
                'date' => '2026-09-21',
            ])
            ->assertForbidden();

        $this->assertSame(0, Task::query()->count());
    }

    public function test_a_child_cannot_get_into_the_admin(): void
    {
        $child = Person::factory()->create([
            'household_id' => $this->household->id,
            'role' => PersonRole::Child,
        ]);

        $user = $this->user();
        $user->memberships()->update(['person_id' => $child->id]);

        $this->assertFalse($user->refresh()->canAccessPanel(Filament::getDefaultPanel()));
    }

    /** The same device without a sign-in stays behind the PIN. */
    public function test_the_wall_tablet_still_needs_the_pin(): void
    {
        $this->parentWithPin($this->household);

        $device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        $token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];

        $this->withToken($token)
            ->postJson('/api/wall/tasks', [
                'title' => 'Zähne putzen',
                'points' => 2,
                'date' => '2026-09-21',
            ])
            ->assertStatus(423);
    }

    public function test_push_subscription_creates_a_mobile_device(): void
    {
        $token = $this->signIn();

        $this->withToken($token)
            ->postJson('/api/push/subscribe', [
                'endpoint' => 'https://push.example.test/abc',
                'keys' => ['p256dh' => 'oeffentlich', 'auth' => 'geheim'],
                'name' => 'Handy von Alex',
            ])
            ->assertCreated()
            ->assertJsonPath('subscribed', true);

        $this->assertDatabaseHas('devices', [
            'name' => 'Handy von Alex',
            'kind' => DeviceKind::Mobile->value,
            'push_endpoint' => 'https://push.example.test/abc',
        ]);
    }

    /**
     * The same browser subscribes again after a restart. Two entries for it
     * would send every notification twice.
     */
    public function test_resubscribing_the_same_endpoint_stays_one_entry(): void
    {
        $token = $this->signIn();

        foreach ([1, 2] as $ignored) {
            $this->withToken($token)
                ->postJson('/api/push/subscribe', [
                    'endpoint' => 'https://push.example.test/abc',
                    'keys' => ['p256dh' => 'oeffentlich', 'auth' => 'geheim'],
                ])
                ->assertCreated();
        }

        $this->assertSame(1, Device::query()->where('kind', DeviceKind::Mobile->value)->count());
    }

    /**
     * Switching on sends a sample to that phone; the silent re-registration
     * on every start must not.
     */
    public function test_only_switching_on_sends_a_sample_notification(): void
    {
        Queue::fake();
        $token = $this->signIn();
        $subscription = [
            'endpoint' => 'https://push.example.test/abc',
            'keys' => ['p256dh' => 'oeffentlich', 'auth' => 'geheim'],
        ];

        $this->withToken($token)->postJson('/api/push/subscribe', $subscription)->assertCreated();
        Queue::assertNothingPushed();

        $id = $this->withToken($token)
            ->postJson('/api/push/subscribe', [...$subscription, 'confirm' => true])
            ->assertCreated()
            ->json('id');

        Queue::assertPushed(ConfirmPushJob::class, 1);
        $this->assertIsInt($id);
    }

    public function test_unsubscribing_from_push_deletes_the_subscription(): void
    {
        $token = $this->signIn();

        $this->withToken($token)->postJson('/api/push/subscribe', [
            'endpoint' => 'https://push.example.test/abc',
            'keys' => ['p256dh' => 'oeffentlich', 'auth' => 'geheim'],
        ])->assertCreated();

        $this->withToken($token)
            ->deleteJson('/api/push/subscribe', ['endpoint' => 'https://push.example.test/abc'])
            ->assertNoContent();

        $this->assertDatabaseHas('devices', ['push_endpoint' => null]);
    }

    public function test_push_key_reports_missing_configuration(): void
    {
        $token = $this->signIn();

        config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);

        $this->withToken($token)
            ->getJson('/api/push/key')
            ->assertOk()
            ->assertJsonPath('configured', false);
    }

    public function test_no_push_without_sign_in(): void
    {
        $this->postJson('/api/push/subscribe', [
            'endpoint' => 'https://push.example.test/abc',
            'keys' => ['p256dh' => 'oeffentlich', 'auth' => 'geheim'],
        ])->assertUnauthorized();
    }

    /**
     * A browser that paired a wall and holds an account's cookie too stays
     * the wall: the device cookie wins, and `/api/session` says so — the app
     * chooses its shell from it.
     */
    public function test_a_paired_wall_stays_the_wall_when_an_account_cookie_is_present(): void
    {
        $userToken = $this->signIn();
        $device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        $deviceToken = (string) $this->postJson('/api/devices/pair', ['code' => $device->pairing_code])
            ->getCookie(DeviceTokenFromCookie::COOKIE, false)?->getValue();

        Auth::forgetGuards();

        $both = fn () => $this->withCredentials()
            ->withUnencryptedCookie(DeviceTokenFromCookie::COOKIE, $deviceToken)
            ->withUnencryptedCookie(UserTokenFromCookie::COOKIE, $userToken);

        $both()->getJson('/api/session')->assertOk()->assertExactJson(['kind' => 'device']);

        Auth::forgetGuards();

        $both()->getJson('/api/wall/agenda?from=2026-09-14&to=2026-09-20')
            ->assertOk()
            ->assertJsonPath('viewer', 'device');
    }

    /**
     * A tablet deleted in the admin leaves its cookie behind. It must not
     * shadow an account signed in later in the same browser.
     */
    public function test_a_dead_device_cookie_is_cleared_and_stops_shadowing_the_account(): void
    {
        $userToken = $this->signIn();
        Auth::forgetGuards();

        $response = $this->withCredentials()
            ->withUnencryptedCookie(DeviceTokenFromCookie::COOKIE, '999|gone')
            ->withUnencryptedCookie(UserTokenFromCookie::COOKIE, $userToken)
            ->getJson('/api/session')
            ->assertUnauthorized();

        $this->assertTrue($response->getCookie(DeviceTokenFromCookie::COOKIE, false)?->isCleared());

        // The browser drops the cleared cookie; the test client keeps it.
        Auth::forgetGuards();
        $this->unencryptedCookies = [];
        $this->withCredentials()
            ->withUnencryptedCookie(UserTokenFromCookie::COOKIE, $userToken)
            ->getJson('/api/session')
            ->assertOk()
            ->assertExactJson(['kind' => 'user']);
    }

    public function test_a_live_device_keeps_its_cookie_on_a_refusal(): void
    {
        $device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        $deviceToken = (string) $this->postJson('/api/devices/pair', ['code' => $device->pairing_code])
            ->getCookie(DeviceTokenFromCookie::COOKIE, false)?->getValue();
        Auth::forgetGuards();

        $response = $this->withCredentials()
            ->withUnencryptedCookie(DeviceTokenFromCookie::COOKIE, $deviceToken)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();

        $this->assertNull($response->getCookie(DeviceTokenFromCookie::COOKIE, false));
    }

    public function test_the_session_names_an_account(): void
    {
        $token = $this->signIn();

        Auth::forgetGuards();

        $this->withCredentials()
            ->withUnencryptedCookie(UserTokenFromCookie::COOKIE, $token)
            ->getJson('/api/session')
            ->assertOk()
            ->assertExactJson(['kind' => 'user']);
    }

    public function test_the_session_is_unknown_without_a_cookie(): void
    {
        $this->getJson('/api/session')->assertUnauthorized();
    }
}
