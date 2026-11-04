<?php

declare(strict_types=1);

namespace Tests\Feature\Wall;

use App\Enums\ConnectionStatus;
use App\Enums\EventOrigin;
use App\Integrations\Calendar\Google\EventProperties;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\Household;
use App\Models\Person;
use App\Models\ProviderAccount;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The write path: the display becomes an interface.
 *
 * Both are checked -- what arrives at Google and what is created locally.
 * Checking only the database would let slip that the event never shows up
 * in the phone calendar.
 */
final class WallEventWriteTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private Calendar $calendar;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create(['timezone' => 'Europe/Vienna']);

        app(HouseholdContext::class)->setId((int) $this->household->id);

        $source = ProviderAccount::factory()->create(['household_id' => $this->household->id]);

        $this->calendar = Calendar::factory()->create([
            'household_id' => $this->household->id,
            'provider_account_id' => $source->id,
            'external_id' => 'familie@group.calendar.google.com',
            'timezone' => 'Europe/Vienna',
        ]);

        $device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        $this->token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];
    }

    /** @param array<string, mixed> $response */
    private function googleResponds(array $response = [], int $status = 200): void
    {
        Http::fake([
            'https://www.googleapis.com/calendar/v3/*' => Http::response([
                'id' => 'evt-new',
                'etag' => '"9911223344556677"',
                'updated' => '2026-09-14T12:00:00.000Z',
                ...$response,
            ], $status),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function create(array $data = []): TestResponse
    {
        return $this->withToken($this->token)->postJson('/api/wall/events', [
            'calendar_id' => $this->calendar->id,
            'title' => 'Elternabend',
            'starts_at' => '2026-09-22T18:00:00+02:00',
            'ends_at' => '2026-09-22T20:00:00+02:00',
            ...$data,
        ]);
    }

    public function test_no_access_without_token(): void
    {
        $this->postJson('/api/wall/events', [])->assertUnauthorized();
    }

    public function test_event_is_created_at_google_and_locally(): void
    {
        $this->googleResponds();

        $this->create()
            ->assertCreated()
            ->assertJsonPath('title', 'Elternabend');

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return $request->method() === 'POST'
                && str_contains($request->url(), 'familie%40group.calendar.google.com/events')
                && $body['summary'] === 'Elternabend'
                && $body['start']['timeZone'] === 'Europe/Vienna';
        });

        $event = Event::query()->firstOrFail();

        $this->assertSame(EventOrigin::App, $event->origin);
        $this->assertSame('evt-new', $event->external_id);
        $this->assertSame('"9911223344556677"', $event->external_version);
        $this->assertNotNull($event->revision_marker);
    }

    public function test_all_day_event_uses_date_fields(): void
    {
        $this->googleResponds();

        $this->create([
            'all_day' => true,
            'starts_at' => '2026-09-22T00:00:00+02:00',
            'ends_at' => '2026-09-23T00:00:00+02:00',
        ])->assertCreated();

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            // Google's end date is exclusive: one day, not two.
            return $body['start']['date'] === '2026-09-22'
                && $body['end']['date'] === '2026-09-23'
                && ! isset($body['start']['dateTime']);
        });
    }

    public function test_person_assignment_is_mirrored_to_google(): void
    {
        $this->googleResponds();

        $person = Person::factory()->create(['household_id' => $this->household->id]);

        $this->create(['people' => [$person->id]])->assertCreated();

        Http::assertSent(fn (Request $request): bool => $request->data()['extendedProperties']['private'][EventProperties::PEOPLE]
            === (string) $person->id);

        $this->assertTrue(Event::query()->firstOrFail()->people->contains($person));
    }

    public function test_foreign_person_is_not_assigned(): void
    {
        $this->googleResponds();

        $foreign = Person::factory()->create([
            'household_id' => Household::factory()->create()->id,
        ]);

        $this->create(['people' => [$foreign->id]])->assertCreated();

        $this->assertTrue(Event::query()->firstOrFail()->people->isEmpty());
    }

    public function test_foreign_calendar_is_not_written(): void
    {
        $this->googleResponds();

        $foreignHousehold = Household::factory()->create();
        $foreignCalendar = Calendar::factory()->create([
            'household_id' => $foreignHousehold->id,
            'provider_account_id' => ProviderAccount::factory()->create([
                'household_id' => $foreignHousehold->id,
            ])->id,
        ]);

        $this->create(['calendar_id' => $foreignCalendar->id])->assertNotFound();

        $this->assertSame(0, Event::withoutHouseholdScope()->count());
    }

    public function test_update_sends_the_etag(): void
    {
        $event = $this->existingEvent();

        Http::fake([
            'https://www.googleapis.com/calendar/v3/*' => Http::response([
                'id' => 'evt-old',
                'etag' => '"new-after-patch"',
            ]),
        ]);

        $this->withToken($this->token)
            ->patchJson("/api/wall/events/{$event->id}", [
                'title' => 'Elternabend verschoben',
                'starts_at' => '2026-09-23T18:00:00+02:00',
                'ends_at' => '2026-09-23T20:00:00+02:00',
            ])
            ->assertOk()
            ->assertJsonPath('title', 'Elternabend verschoben');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && $request->header('If-Match')[0] === '"old-1234"');

        $this->assertSame('"new-after-patch"', $event->fresh()->external_version);
    }

    public function test_google_wins_on_conflict(): void
    {
        $event = $this->existingEvent();

        Http::fake(function (Request $request) {
            // First Google rejects the change, then it returns the state
            // that is actually stored there.
            return $request->method() === 'PATCH'
                ? Http::response(['error' => 'conditionNotMet'], 412)
                : Http::response([
                    'id' => 'evt-old',
                    'etag' => '"foreign-9999"',
                    'summary' => 'Von jemand anderem geändert',
                ]);
        });

        $this->withToken($this->token)
            ->patchJson("/api/wall/events/{$event->id}", [
                'title' => 'Meine Fassung',
                'starts_at' => '2026-09-23T18:00:00+02:00',
                'ends_at' => '2026-09-23T20:00:00+02:00',
            ])
            ->assertStatus(409)
            ->assertJsonPath('event.title', 'Von jemand anderem geändert');

        $this->assertSame('Von jemand anderem geändert', $event->fresh()->title);
    }

    public function test_delete_removes_locally_and_at_google(): void
    {
        $event = $this->existingEvent();

        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response([], 204)]);

        $this->withToken($this->token)
            ->deleteJson("/api/wall/events/{$event->id}")
            ->assertNoContent();

        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE');

        $this->assertNull($event->fresh());
    }

    public function test_event_already_deleted_at_google_is_no_error(): void
    {
        $event = $this->existingEvent();

        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response([], 410)]);

        $this->withToken($this->token)
            ->deleteJson("/api/wall/events/{$event->id}")
            ->assertNoContent();

        $this->assertNull($event->fresh());
    }

    public function test_disconnected_account_reports_clearly(): void
    {
        $this->googleResponds();

        $this->calendar->source->forceFill([
            'status' => ConnectionStatus::InvalidGrant,
        ])->save();

        // 422 instead of 500: this is a usage error, not a crash. Otherwise
        // the wall would report "Der Server hat nicht geantwortet" and send
        // the household in the wrong direction.
        $this->create()
            ->assertStatus(422)
            ->assertJsonValidationErrors('calendar_id');

        $this->assertSame(0, Event::query()->count());
    }

    private function existingEvent(): Event
    {
        return Event::factory()->create([
            'household_id' => $this->household->id,
            'calendar_id' => $this->calendar->id,
            'external_id' => 'evt-old',
            'external_version' => '"old-1234"',
            'title' => 'Elternabend',
        ]);
    }
}
