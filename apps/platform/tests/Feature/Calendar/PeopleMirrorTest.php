<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Integrations\Calendar\Google\EventProperties;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\Household;
use App\Models\Person;
use App\Models\ProviderAccount;
use App\Services\Calendar\CalendarSyncService;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The way back of the mirrored person assignment.
 *
 * Without it the mirroring would be worthless: after `410 Gone` the stored
 * events are cleared and fetched again. The assignment would still sit on the
 * Google event but never arrive in the database again — and on the wall all
 * events would silently lose their colour.
 */
final class PeopleMirrorTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private Calendar $calendar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();

        app(HouseholdContext::class)->setId((int) $this->household->id);

        $this->calendar = Calendar::factory()->create([
            'household_id' => $this->household->id,
            'provider_account_id' => ProviderAccount::factory()->create([
                'household_id' => $this->household->id,
            ])->id,
            'external_id' => 'familie@group.calendar.google.com',
            'timezone' => 'Europe/Vienna',
        ]);
    }

    /** @param array<string, string> $private */
    private function googleReturns(array $private): void
    {
        Http::fake([
            'https://www.googleapis.com/calendar/v3/*' => Http::response([
                'items' => [[
                    'id' => 'evt-1',
                    'status' => 'confirmed',
                    'etag' => '"1234"',
                    'summary' => 'Schulausflug',
                    'start' => ['dateTime' => '2026-09-20T09:00:00+02:00'],
                    'end' => ['dateTime' => '2026-09-20T15:00:00+02:00'],
                    'extendedProperties' => ['private' => $private],
                ]],
                'nextSyncToken' => 'TOKEN',
            ]),
        ]);
    }

    public function test_mirrored_assignment_is_applied(): void
    {
        $child = Person::factory()->create(['household_id' => $this->household->id]);

        $this->googleReturns([EventProperties::PEOPLE => (string) $child->id]);

        app(CalendarSyncService::class)->sync($this->calendar);

        $this->assertTrue(Event::query()->firstOrFail()->people->contains($child));
    }

    public function test_foreign_person_is_not_applied(): void
    {
        $foreign = Person::factory()->create([
            'household_id' => Household::factory()->create()->id,
        ]);

        $this->googleReturns([EventProperties::PEOPLE => (string) $foreign->id]);

        app(CalendarSyncService::class)->sync($this->calendar);

        $this->assertTrue(Event::query()->firstOrFail()->people->isEmpty());
    }

    public function test_without_property_the_assignment_stays_untouched(): void
    {
        $child = Person::factory()->create(['household_id' => $this->household->id]);

        $event = Event::factory()->create([
            'household_id' => $this->household->id,
            'calendar_id' => $this->calendar->id,
            'external_id' => 'evt-1',
        ]);

        $event->people()->sync([$child->id]);

        // An event created by hand in Google does not carry the property.
        // It must therefore not delete an existing assignment.
        $this->googleReturns([]);

        app(CalendarSyncService::class)->sync($this->calendar);

        $this->assertTrue($event->fresh()->people->contains($child));
    }
}
