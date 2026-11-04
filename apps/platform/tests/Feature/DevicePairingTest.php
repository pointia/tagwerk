<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeviceKind;
use App\Http\Middleware\DeviceTokenFromCookie;
use App\Models\Household;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class DevicePairingTest extends TestCase
{
    use RefreshDatabase;

    private function householdWithContext(): Household
    {
        $household = Household::factory()->create();
        app(HouseholdContext::class)->setId($household->id);

        return $household;
    }

    public function test_pairing_code_can_be_redeemed(): void
    {
        $household = $this->householdWithContext();
        $device = app(DevicePairingService::class)->createCode($household, 'Küche');

        $this->postJson('/api/devices/pair', ['code' => $device->pairing_code])
            ->assertOk()
            ->assertJsonStructure(['household_id'])
            ->assertJson(['household_id' => $household->id]);

        $device->refresh();

        $this->assertNotNull($device->paired_at);
        $this->assertNull($device->pairing_code);
    }

    public function test_expired_code_is_rejected(): void
    {
        $household = $this->householdWithContext();
        $device = app(DevicePairingService::class)->createCode($household, 'Flur');
        $device->forceFill(['pairing_expires_at' => Carbon::now()->subMinute()])->save();

        $this->postJson('/api/devices/pair', ['code' => $device->pairing_code])
            ->assertStatus(422);
    }

    public function test_code_is_valid_only_once(): void
    {
        $household = $this->householdWithContext();
        $device = app(DevicePairingService::class)->createCode($household, 'Wohnzimmer');
        $code = $device->pairing_code;

        $this->postJson('/api/devices/pair', ['code' => $code])->assertOk();
        $this->postJson('/api/devices/pair', ['code' => $code])->assertStatus(422);
    }

    public function test_unknown_code_is_rejected(): void
    {
        $this->postJson('/api/devices/pair', ['code' => 'XXXXXXXX'])
            ->assertStatus(422);
    }

    public function test_device_token_sets_the_household_context(): void
    {
        $household = $this->householdWithContext();
        $device = app(DevicePairingService::class)->createCode($household, 'Küche');

        // The token is only in the httpOnly cookie, not in the response body.
        $response = $this->postJson('/api/devices/pair', ['code' => $device->pairing_code]);

        $token = '';

        foreach ($response->baseResponse->headers->getCookies() as $cookie) {
            if ($cookie->getName() === DeviceTokenFromCookie::COOKIE) {
                $token = (string) $cookie->getValue();
            }
        }

        app(HouseholdContext::class)->setId(null);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/devices/me')
            ->assertOk()
            ->assertJson([
                'household_id' => $household->id,
                'kind' => DeviceKind::Wall->value,
                'name' => 'Küche',
            ]);
    }

    public function test_no_access_to_device_data_without_token(): void
    {
        $this->getJson('/api/devices/me')->assertStatus(401);
    }

    public function test_pairing_code_contains_no_confusable_characters(): void
    {
        $household = $this->householdWithContext();
        $device = app(DevicePairingService::class)->createCode($household, 'Bad');

        $this->assertSame(8, mb_strlen($device->pairing_code));
        $this->assertDoesNotMatchRegularExpression('/[01OI]/', $device->pairing_code);
    }

    private function pairedToken(Household $household): string
    {
        $device = app(DevicePairingService::class)->createCode($household, 'Flur');

        return (string) $this->postJson('/api/devices/pair', ['code' => $device->pairing_code])
            ->getCookie(DeviceTokenFromCookie::COOKIE, false)?->getValue();
    }

    /**
     * Unpairing is the way from the tablet to the phone's sign-in in the same
     * browser. It sits behind the parent PIN: a child unpairing the hallway
     * tablet would leave the family with a start page.
     */
    public function test_unpairing_needs_the_parent_pin(): void
    {
        $household = $this->householdWithContext();
        $this->parentWithPin($household);
        $token = $this->pairedToken($household);

        app(HouseholdContext::class)->setId(null);

        $this->withToken($token)->deleteJson('/api/devices/me')->assertStatus(423);
    }

    public function test_unpairing_revokes_the_token_and_drops_the_cookie(): void
    {
        $household = $this->householdWithContext();
        $this->parentWithPin($household);
        $token = $this->pairedToken($household);

        app(HouseholdContext::class)->setId(null);

        $this->withToken($token)->postJson('/api/wall/pin', ['pin' => '1234'])->assertOk();

        $response = $this->withToken($token)->deleteJson('/api/devices/me')->assertOk();

        $this->assertTrue($response->getCookie(DeviceTokenFromCookie::COOKIE, false)?->isCleared());

        Auth::forgetGuards();

        $this->withToken($token)->getJson('/api/session')->assertUnauthorized();
    }
}
