<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\AdminFromAppCookie;
use App\Http\Middleware\UserTokenFromCookie;
use App\Models\Device;
use App\Models\Household;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Whoever is signed in on the phone opens the admin without a second
 * sign-in — as long as the account may enter the panel at all.
 */
final class AdminFromAppTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();
    }

    public function test_a_parent_signed_in_on_the_phone_enters_the_admin(): void
    {
        $parent = $this->account(Person::factory()->create(['household_id' => $this->household->id]));
        $token = $parent->createToken('phone')->plainTextToken;

        $this->withUnencryptedCookie(UserTokenFromCookie::COOKIE, $token)
            ->get('/admin')
            ->assertSuccessful();

        $this->assertAuthenticatedAs($parent, 'web');
    }

    public function test_a_child_stays_outside(): void
    {
        $child = $this->account(Person::factory()->child()->create(['household_id' => $this->household->id]));
        $token = $child->createToken('phone')->plainTextToken;

        $this->withUnencryptedCookie(UserTokenFromCookie::COOKIE, $token)
            ->get('/admin')
            ->assertRedirect();

        $this->assertGuest('web');
    }

    public function test_a_device_token_signs_nobody_in(): void
    {
        $device = Device::factory()->create(['household_id' => $this->household->id]);
        $token = $device->createToken('wall')->plainTextToken;

        $this->withUnencryptedCookie(UserTokenFromCookie::COOKIE, $token)
            ->get('/admin')
            ->assertRedirect();

        $this->assertGuest('web');
    }

    public function test_an_expired_token_signs_nobody_in(): void
    {
        $parent = $this->account(Person::factory()->create(['household_id' => $this->household->id]));
        $token = $parent->createToken('phone', expiresAt: Carbon::now()->subMinute())->plainTextToken;

        $this->withUnencryptedCookie(UserTokenFromCookie::COOKIE, $token)
            ->get('/admin')
            ->assertRedirect();

        $this->assertGuest('web');
    }

    /** Signing out in the app ends the admin session it opened. */
    public function test_signing_out_of_the_app_ends_the_admin_too(): void
    {
        $parent = $this->account(Person::factory()->create(['household_id' => $this->household->id]));
        $access = $parent->createToken('phone')->accessToken;
        $access->delete();

        $this->actingAs($parent, 'web')
            ->withSession([AdminFromAppCookie::SESSION => $access->getKey()])
            ->get('/admin')
            ->assertRedirect();

        $this->assertGuest('web');
    }

    /**
     * Signing out in the admin signs this browser out of the app too —
     * otherwise the app's cookie signed the admin straight back in.
     */
    public function test_signing_out_of_the_admin_ends_the_app_sign_in(): void
    {
        $parent = $this->account(Person::factory()->create(['household_id' => $this->household->id]));
        $token = $parent->createToken('phone')->plainTextToken;

        $this->withUnencryptedCookie(UserTokenFromCookie::COOKIE, $token)->get('/admin')->assertSuccessful();

        $this->withUnencryptedCookie(UserTokenFromCookie::COOKIE, $token)
            ->post('/admin/logout')
            ->assertRedirect('/')
            ->assertCookieExpired(UserTokenFromCookie::COOKIE);

        $this->assertSame(0, $parent->tokens()->count());

        Auth::forgetGuards();

        $this->withUnencryptedCookie(UserTokenFromCookie::COOKIE, $token)->get('/admin')->assertRedirect();
        $this->assertGuest('web');
    }

    /** Without the app's cookie the admin keeps its own way out. */
    public function test_a_password_session_signs_out_to_the_admin_login(): void
    {
        $parent = $this->account(Person::factory()->create(['household_id' => $this->household->id]));

        $this->actingAs($parent, 'web')
            ->post('/admin/logout')
            ->assertRedirect('/admin/login');
    }

    /** A password sign-in in the admin has nothing to do with the app. */
    public function test_a_password_session_stays_without_a_token(): void
    {
        $parent = $this->account(Person::factory()->create(['household_id' => $this->household->id]));

        $this->actingAs($parent, 'web')->get('/admin')->assertSuccessful();

        $this->assertAuthenticatedAs($parent, 'web');
    }

    /** The way back: the header leads to the app. */
    public function test_the_header_leads_back_to_the_app(): void
    {
        $parent = $this->account(Person::factory()->create(['household_id' => $this->household->id]));

        $this->actingAs($parent, 'web')
            ->get('/admin')
            ->assertSee('data-testid="to-app"', false)
            ->assertSee(__('To the app'));
    }

    private function account(Person $person): User
    {
        return User::factory()->create(['household_id' => $this->household->id, 'person_id' => $person->id]);
    }
}
