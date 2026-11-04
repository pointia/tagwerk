<?php

declare(strict_types=1);

namespace Tests\Feature\Wall;

use App\Http\Middleware\DeviceTokenFromCookie;
use App\Models\Household;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * The device token must never reach JavaScript.
 *
 * CAUTION: getJson() sends NO cookies without withCredentials() —
 * prepareCookiesForJsonRequest() then returns an empty array. Without the
 * call cookie tests pass vacuously because no cookie arrives at all.
 */
final class DeviceCookieAuthTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create([
            'timezone' => 'Europe/Vienna',
            'holiday_region' => 'AT-4',
        ]);

        app(HouseholdContext::class)->setId((int) $this->household->id);
    }

    private function pair(string $name = 'Küche'): TestResponse
    {
        $device = app(DevicePairingService::class)->createCode($this->household, $name);

        return $this->postJson('/api/devices/pair', ['code' => $device->pairing_code]);
    }

    private function cookieFrom(TestResponse $response): ?Cookie
    {
        foreach ($response->baseResponse->headers->getCookies() as $cookie) {
            if ($cookie->getName() === DeviceTokenFromCookie::COOKIE) {
                return $cookie;
            }
        }

        return null;
    }

    public function test_pairing_sets_an_httponly_cookie(): void
    {
        $cookie = $this->cookieFrom($this->pair()->assertOk());

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly(), 'The device token must be httpOnly.');
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertNotSame('', (string) $cookie->getValue());
    }

    public function test_the_token_is_not_in_the_response_body(): void
    {
        $response = $this->pair()->assertOk();

        // Otherwise it would inevitably end up in localStorage.
        $response->assertJsonMissingPath('token');

        $body = $response->getContent();
        $token = (string) $this->cookieFrom($response)?->getValue();

        $this->assertStringNotContainsString($token, (string) $body);
    }

    public function test_the_cookie_alone_is_enough_for_access(): void
    {
        $token = (string) $this->cookieFrom($this->pair())?->getValue();

        // No Authorization header — only the cookie, as in the browser.
        $this->withCredentials()->withUnencryptedCookie(DeviceTokenFromCookie::COOKIE, $token)
            ->getJson('/api/devices/me')
            ->assertOk()
            ->assertJsonPath('name', 'Küche');
    }

    public function test_no_access_without_cookie(): void
    {
        $this->pair();

        $this->getJson('/api/devices/me')->assertUnauthorized();
    }

    public function test_invalid_cookie_is_rejected(): void
    {
        $this->withCredentials()->withUnencryptedCookie(DeviceTokenFromCookie::COOKIE, 'made-up')
            ->getJson('/api/devices/me')
            ->assertUnauthorized();
    }

    public function test_explicit_header_takes_precedence_over_the_cookie(): void
    {
        $own = (string) $this->cookieFrom($this->pair('Küche'))?->getValue();
        $foreign = (string) $this->cookieFrom($this->pair('Flur'))?->getValue();

        $this->withCredentials()->withUnencryptedCookie(DeviceTokenFromCookie::COOKIE, $own)
            ->withToken($foreign)
            ->getJson('/api/devices/me')
            ->assertOk()
            ->assertJsonPath('name', 'Flur');
    }

    public function test_the_wall_view_is_reachable_with_the_cookie(): void
    {
        $token = (string) $this->cookieFrom($this->pair())?->getValue();

        $this->withCredentials()->withUnencryptedCookie(DeviceTokenFromCookie::COOKIE, $token)
            ->getJson('/api/wall/agenda?from=2026-09-14&to=2026-09-20')
            ->assertOk()
            ->assertJsonPath('household.timezone', 'Europe/Vienna');
    }
}
