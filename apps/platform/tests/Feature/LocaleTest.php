<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Household;
use App\Models\User;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The language follows a chain: own choice, then the household's.
 *
 * The validation message is the real proof: `locale` in a payload only says
 * what was computed, the message says what the request actually ran in.
 */
final class LocaleTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create(['locale' => 'en']);
        app(HouseholdContext::class)->setId((int) $this->household->id);
    }

    public function test_an_account_without_a_choice_follows_the_household(): void
    {
        $this->signIn(User::factory()->create(['household_id' => $this->household->id]));

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('locale_preference', null);

        $this->putJson('/api/auth/locale', ['locale' => 'ja'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.locale.0', 'The selected locale is invalid.');
    }

    public function test_the_own_choice_wins_over_the_household(): void
    {
        $this->signIn(User::factory()->create(['household_id' => $this->household->id, 'locale' => 'de']));

        $this->getJson('/api/auth/me')->assertJsonPath('locale', 'de');

        $this->putJson('/api/auth/locale', ['locale' => 'ja'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.locale.0', 'Die Auswahl für locale ist ungültig.');
    }

    public function test_the_phone_sets_and_resets_its_language(): void
    {
        $this->signIn(User::factory()->create(['household_id' => $this->household->id]));

        $this->putJson('/api/auth/locale', ['locale' => 'de'])
            ->assertOk()
            ->assertJsonPath('locale', 'de')
            ->assertJsonPath('locale_preference', 'de');

        $this->putJson('/api/auth/locale', ['locale' => null])
            ->assertOk()
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('locale_preference', null);
    }

    public function test_a_tablet_may_differ_from_the_household(): void
    {
        $this->signIn(Device::factory()->create(['household_id' => $this->household->id, 'locale' => 'de']));

        $this->getJson('/api/devices/me')->assertJsonPath('locale', 'de');
        // The wall learns it with every agenda fetch, so a change reaches it without re-pairing.
        $this->getJson('/api/wall/agenda?from=2026-09-28&to=2026-09-28')
            ->assertJsonPath('locale', 'de')
            ->assertJsonPath('household.areas.1.label', 'Kalender');
    }

    public function test_mails_go_out_in_the_recipients_language(): void
    {
        $user = User::factory()->create(['household_id' => $this->household->id]);

        $this->assertSame('en', $user->preferredLocale());

        $user->update(['locale' => 'de']);

        $this->assertSame('de', $user->preferredLocale());
    }

    private function signIn(User|Device $actor): void
    {
        $this->withToken($actor->createToken('test')->plainTextToken);
    }
}
