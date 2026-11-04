<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\Event;
use App\Models\Household;
use App\Models\ProviderAccount;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Timestamps must survive the trip into the database and back as the same
 * instant.
 *
 * Laravel's default format for casts is "Y-m-d H:i:s" — without a zone
 * offset. Carbon then formats in its own zone, PostgreSQL reads the result in
 * the session zone UTC: Vienna midnight becomes 02:00 Vienna time. The wall
 * clock time survives, the instant does not.
 *
 * Existing tests did not notice because they only check date and title — and
 * an instant shifted by two hours usually keeps its date.
 */
final class TimestampRoundTripTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create(['timezone' => 'Europe/Vienna']);

        app(HouseholdContext::class)->setId((int) $this->household->id);
    }

    public function test_event_keeps_its_instant(): void
    {
        $vienna = CarbonImmutable::parse('2026-12-01 00:00', 'Europe/Vienna');
        $event = $this->event($vienna)->fresh();

        $this->assertTrue(
            $event->starts_at->equalTo($vienna),
            'Stored '.$event->starts_at->toIso8601String().', expected '.$vienna->toIso8601String(),
        );
    }

    public function test_all_day_event_stays_at_vienna_midnight(): void
    {
        // Exactly the case that occupied two cells on the wall instead of one.
        $start = CarbonImmutable::parse('2026-12-01 00:00', 'Europe/Vienna');
        $event = $this->event($start, $start->addDay(), ['all_day' => true])->fresh();

        $this->assertSame('00:00', $event->starts_at->setTimezone('Europe/Vienna')->format('H:i'));
        $this->assertSame('00:00', $event->ends_at->setTimezone('Europe/Vienna')->format('H:i'));
    }

    public function test_summer_and_winter_time_alike(): void
    {
        // Vienna is at +02:00 in summer and +01:00 in winter. A hard-wired
        // offset would miss exactly one of the two cases.
        foreach (['2026-07-01 00:00', '2026-12-01 00:00'] as $value) {
            $vienna = CarbonImmutable::parse($value, 'Europe/Vienna');
            $event = $this->event($vienna)->fresh();

            $this->assertTrue($event->starts_at->equalTo($vienna), $value);
        }
    }

    public function test_other_models_with_time_zone_columns_too(): void
    {
        $instant = CarbonImmutable::parse('2026-12-01 09:15', 'Europe/Vienna');
        $calendar = $this->calendar(['last_synced_at' => $instant])->fresh();

        $this->assertTrue($calendar->last_synced_at->equalTo($instant));
    }

    private function event(
        CarbonImmutable $from,
        ?CarbonImmutable $to = null,
        array $attributes = [],
    ): Event {
        return Event::factory()->create([
            'household_id' => $this->household->id,
            'calendar_id' => $this->calendar()->id,
            'starts_at' => $from,
            'ends_at' => $to ?? $from->addHour(),
            ...$attributes,
        ]);
    }

    private function calendar(array $attributes = []): Calendar
    {
        return Calendar::factory()->create([
            'household_id' => $this->household->id,
            'provider_account_id' => ProviderAccount::factory()->create([
                'household_id' => $this->household->id,
            ])->id,
            ...$attributes,
        ]);
    }
}
