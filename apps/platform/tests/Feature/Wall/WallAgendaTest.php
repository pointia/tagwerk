<?php

declare(strict_types=1);

namespace Tests\Feature\Wall;

use App\Enums\ConnectionStatus;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\Household;
use App\Models\Person;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class WallAgendaTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private ProviderAccount $source;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->household = Household::factory()->create([
            'timezone' => 'Europe/Vienna',
            'holiday_region' => 'AT-4',
        ]);

        app(HouseholdContext::class)->setId((int) $this->household->id);

        $this->source = ProviderAccount::factory()->create([
            'household_id' => $this->household->id,
        ]);

        // The wall signs in as a device, not as a person.
        $device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        $this->token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];
    }

    private function calendar(array $attributes = []): Calendar
    {
        return Calendar::factory()->create([
            'household_id' => $this->household->id,
            'provider_account_id' => $this->source->id,
            ...$attributes,
        ]);
    }

    /**
     * The wall only offers for creation what it can actually write to.
     *
     * Without this flag a calendar of a signed-out account would be offered,
     * and the rejection would only come once everything has been typed.
     */
    public function test_calendar_of_a_signed_out_account_is_not_writable(): void
    {
        $this->calendar(['summary' => 'Lebt']);

        $dead = ProviderAccount::factory()->create([
            'household_id' => $this->household->id,
            'status' => ConnectionStatus::InvalidGrant,
        ]);

        $this->calendar(['summary' => 'Abgemeldet', 'provider_account_id' => $dead->id]);

        $response = $this->fetch()->assertOk();

        $calendar = collect($response->json('calendars'))->keyBy('summary');

        $this->assertTrue($calendar['Lebt']['writable']);
        $this->assertFalse($calendar['Abgemeldet']['writable']);
    }

    private function fetch(string $from = '2026-09-14', string $to = '2026-09-20'): TestResponse
    {
        return $this->withToken($this->token)
            ->getJson("/api/wall/agenda?from={$from}&to={$to}");
    }

    public function test_no_access_without_token(): void
    {
        $this->getJson('/api/wall/agenda?from=2026-09-14&to=2026-09-20')
            ->assertUnauthorized();
    }

    public function test_events_in_range_are_delivered(): void
    {
        $calendar = $this->calendar(['summary' => 'Familie', 'color' => '#616161']);

        Event::factory()->create([
            'household_id' => $this->household->id,
            'calendar_id' => $calendar->id,
            'title' => 'Elternabend',
            'starts_at' => CarbonImmutable::parse('2026-09-16 19:00', 'Europe/Vienna'),
            'ends_at' => CarbonImmutable::parse('2026-09-16 20:30', 'Europe/Vienna'),
        ]);

        $response = $this->fetch()->assertSuccessful();

        $response->assertJsonPath('household.timezone', 'Europe/Vienna');
        $response->assertJsonCount(1, 'events');
        $response->assertJsonPath('events.0.title', 'Elternabend');
        $response->assertJsonPath('events.0.color', '#616161');
    }

    public function test_events_outside_the_range_are_missing(): void
    {
        $calendar = $this->calendar();

        Event::factory()->create([
            'household_id' => $this->household->id,
            'calendar_id' => $calendar->id,
            'starts_at' => CarbonImmutable::parse('2026-11-01 10:00', 'Europe/Vienna'),
            'ends_at' => CarbonImmutable::parse('2026-11-01 11:00', 'Europe/Vienna'),
        ]);

        $this->fetch()->assertSuccessful()->assertJsonCount(0, 'events');
    }

    public function test_event_reaching_into_the_range_counts(): void
    {
        $calendar = $this->calendar();

        // Starts before, ends in the middle of the range.
        Event::factory()->create([
            'household_id' => $this->household->id,
            'calendar_id' => $calendar->id,
            'title' => 'Urlaub',
            'starts_at' => CarbonImmutable::parse('2026-09-10 00:00', 'Europe/Vienna'),
            'ends_at' => CarbonImmutable::parse('2026-09-17 00:00', 'Europe/Vienna'),
        ]);

        $this->fetch()->assertSuccessful()->assertJsonPath('events.0.title', 'Urlaub');
    }

    public function test_deselected_calendar_does_not_appear_on_the_wall(): void
    {
        $calendar = $this->calendar(['is_active' => false]);

        Event::factory()->create([
            'household_id' => $this->household->id,
            'calendar_id' => $calendar->id,
            'starts_at' => CarbonImmutable::parse('2026-09-16 19:00', 'Europe/Vienna'),
            'ends_at' => CarbonImmutable::parse('2026-09-16 20:00', 'Europe/Vienna'),
        ]);

        $response = $this->fetch()->assertSuccessful();

        $response->assertJsonCount(0, 'events');

        // The builtin calendar is always included; the deselected one must not be.
        $this->assertNotContains(
            $calendar->id,
            array_column((array) $response->json('calendars'), 'id'),
        );
    }

    public function test_color_of_the_assigned_person_wins(): void
    {
        $child = Person::factory()->child()->create([
            'household_id' => $this->household->id,
            'color' => '#2E7D32',
        ]);

        $calendar = $this->calendar(['color' => '#616161']);

        $event = Event::factory()->create([
            'household_id' => $this->household->id,
            'calendar_id' => $calendar->id,
            'starts_at' => CarbonImmutable::parse('2026-09-16 16:30', 'Europe/Vienna'),
            'ends_at' => CarbonImmutable::parse('2026-09-16 18:00', 'Europe/Vienna'),
        ]);

        $event->people()->attach($child);

        $response = $this->fetch()->assertSuccessful();

        $response->assertJsonPath('events.0.color', '#2E7D32');
        $response->assertJsonPath('events.0.people', [$child->id]);
    }

    public function test_public_holidays_of_the_state_are_included(): void
    {
        $this->fetch('2026-10-20', '2026-10-31')
            ->assertSuccessful()
            ->assertJsonPath('holidays.0.name', 'Nationalfeiertag')
            ->assertJsonPath('holidays.0.date', '2026-10-26');
    }

    public function test_lost_grant_is_reported(): void
    {
        $this->source->forceFill(['status' => ConnectionStatus::InvalidGrant])->save();

        $this->fetch()
            ->assertSuccessful()
            ->assertJsonPath('household.notices.0.level', 'warning');
    }

    public function test_foreign_households_stay_invisible(): void
    {
        $foreign = Household::factory()->create();

        $foreignSource = ProviderAccount::withoutHouseholdScope()->create([
            'household_id' => $foreign->id,
            'provider' => 'google',
            'account_label' => 'fremd@example.test',
            'status' => ConnectionStatus::Active,
        ]);

        $foreignCalendar = Calendar::withoutHouseholdScope()->create([
            'household_id' => $foreign->id,
            'provider_account_id' => $foreignSource->id,
            'external_id' => 'fremd@example.test',
            'summary' => 'Fremdkalender',
            'is_active' => true,
        ]);

        Event::withoutHouseholdScope()->create([
            'household_id' => $foreign->id,
            'calendar_id' => $foreignCalendar->id,
            'title' => 'Geheimtermin',
            'starts_at' => CarbonImmutable::parse('2026-09-16 19:00', 'Europe/Vienna'),
            'ends_at' => CarbonImmutable::parse('2026-09-16 20:00', 'Europe/Vienna'),
        ]);

        $response = $this->fetch()->assertSuccessful();

        $response->assertJsonCount(0, 'events');
        $response->assertJsonMissing(['title' => 'Geheimtermin']);
        $response->assertJsonMissing(['email' => 'fremd@example.test']);
    }

    public function test_range_must_be_valid(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/wall/agenda?from=2026-09-20&to=2026-09-14')
            ->assertStatus(422);
    }

    public function test_range_is_limited(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/wall/agenda?from=2026-01-01&to=2029-01-01')
            ->assertStatus(422);
    }

    public function test_timestamp_for_the_state_is_included(): void
    {
        // Without it the wall could not show "Stand von 14:32".
        $this->fetch()
            ->assertSuccessful()
            ->assertJsonStructure(['generated_at', 'range' => ['from', 'to']]);
    }

    public function test_version_identifier_is_included(): void
    {
        // Basis of the version check: when the value changes, the wall
        // reloads. A wall tablet is otherwise never reloaded.
        config(['app.version' => 'abc1234']);

        $this->fetch()->assertJsonPath('app_version', 'abc1234');
    }

    public function test_viewer_tells_a_device_from_an_account(): void
    {
        // Phone and wall share the cookie path: an account that opens `/`
        // lands on the wall, and the wall sends it on to the PWA.
        $this->fetch()->assertJsonPath('viewer', 'device');

        $user = User::factory()->create(['household_id' => $this->household->id]);
        $this->app['auth']->forgetGuards();

        $this->withToken($user->createToken('phone')->plainTextToken)
            ->getJson('/api/wall/agenda?from=2026-09-29&to=2026-09-30')
            ->assertJsonPath('viewer', 'user');
    }
}
