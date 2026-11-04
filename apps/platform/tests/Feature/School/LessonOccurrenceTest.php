<?php

declare(strict_types=1);

namespace Tests\Feature\School;

use App\Models\Household;
use App\Models\Lesson;
use App\Models\LessonChange;
use App\Models\LessonPeriod;
use App\Models\Person;
use App\Models\SchoolClosure;
use App\Services\School\LessonOccurrence;
use App\Services\School\LessonOccurrenceService;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The occurrence service carries the whole feature: cadence, holidays,
 * public holidays and changes all come together here.
 *
 * All dates lie in the 2026/27 school year. 7 September 2026 is a Monday,
 * 26 October 2026 a Monday and at the same time the Austrian national
 * holiday.
 */
final class LessonOccurrenceTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private Person $lena;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create([
            'timezone' => 'Europe/Vienna',
            'holiday_region' => 'AT',
        ]);

        app(HouseholdContext::class)->setId((int) $this->household->id);

        $this->lena = Person::factory()->create([
            'household_id' => $this->household->id,
            'name' => 'Lena',
        ]);
    }

    /** @return array<int, string> the days on which lessons take place */
    private function days(string $from, string $to, ?Person $child = null): array
    {
        return $this->occurrences($from, $to, $child)
            ->reject(fn (LessonOccurrence $occurrence): bool => $occurrence->isCancelled())
            ->map(fn (LessonOccurrence $occurrence): string => $occurrence->date->toDateString())
            ->values()
            ->all();
    }

    /** @return Collection<int, LessonOccurrence> */
    private function occurrences(string $from, string $to, ?Person $child = null)
    {
        return app(LessonOccurrenceService::class)->between(
            $this->household,
            CarbonImmutable::parse($from, 'Europe/Vienna'),
            CarbonImmutable::parse($to, 'Europe/Vienna'),
            $child,
        );
    }

    private function mondayLesson(array $overrides = []): Lesson
    {
        return Lesson::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $this->lena->id,
            'title' => 'Mathematik',
            'weekday' => 1,
            'anchor_on' => '2026-09-07',
            ...$overrides,
        ]);
    }

    public function test_a_weekly_lesson_appears_every_monday(): void
    {
        $this->mondayLesson();

        $this->assertSame(
            ['2026-09-07', '2026-09-14', '2026-09-21'],
            $this->days('2026-09-07', '2026-09-25'),
        );
    }

    public function test_the_time_is_in_the_household_time_zone(): void
    {
        $this->mondayLesson(['starts_at' => '07:50', 'ends_at' => '08:40']);

        $occurrence = $this->occurrences('2026-09-07', '2026-09-07')->first();

        $this->assertNotNull($occurrence);
        $this->assertSame('07:50', $occurrence->startsAt->format('H:i'));
        $this->assertSame('08:40', $occurrence->endsAt->format('H:i'));
        $this->assertSame('Europe/Vienna', $occurrence->startsAt->timezoneName);
    }

    public function test_holidays_cancel_the_lesson(): void
    {
        $this->mondayLesson();

        SchoolClosure::factory()->create([
            'household_id' => $this->household->id,
            'title' => 'Herbstferien',
            'starts_on' => '2026-09-14',
            'ends_on' => '2026-09-18',
        ]);

        $this->assertSame(
            ['2026-09-07', '2026-09-21'],
            $this->days('2026-09-07', '2026-09-25'),
        );
    }

    /**
     * The national holiday falls on a Monday in 2026. Nobody enters it — it
     * comes from Yasumi.
     */
    public function test_a_public_holiday_cancels_the_lesson_without_an_entry(): void
    {
        $this->mondayLesson();

        $this->assertSame(
            ['2026-10-19', '2026-11-02'],
            $this->days('2026-10-19', '2026-11-02'),
        );
    }

    public function test_a_day_off_for_one_child_leaves_the_other_untouched(): void
    {
        $max = Person::factory()->create([
            'household_id' => $this->household->id,
            'name' => 'Max',
        ]);

        $this->mondayLesson();

        Lesson::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $max->id,
            'title' => 'Sachunterricht',
            'weekday' => 1,
            'anchor_on' => '2026-09-07',
        ]);

        // Only Lena's school has a day off on this Monday.
        SchoolClosure::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $this->lena->id,
            'title' => 'schulautonomer Tag',
            'starts_on' => '2026-09-14',
            'ends_on' => '2026-09-14',
        ]);

        $this->assertSame([], $this->days('2026-09-14', '2026-09-14', $this->lena));
        $this->assertSame(['2026-09-14'], $this->days('2026-09-14', '2026-09-14', $max));
    }

    /**
     * Both cadence tests lie between January and March 2027: no Austrian
     * public holiday falls on a Monday there, so only the holidays show the
     * difference between the two calculations.
     */
    public function test_the_calendar_cadence_runs_through_the_holidays(): void
    {
        $this->mondayLesson([
            'title' => 'Turnen',
            'interval' => 2,
            'cadence' => 'calendar',
            'anchor_on' => '2027-01-04',
        ]);

        $this->semesterBreak();

        /*
         * 15 February would be due but falls into the holidays and is dropped
         * without replacement. The cadence keeps counting regardless, so next
         * up is an even calendar week again.
         */
        $this->assertSame(
            ['2027-01-04', '2027-01-18', '2027-02-01', '2027-03-01'],
            $this->days('2027-01-04', '2027-03-08'),
        );
    }

    public function test_the_school_week_cadence_skips_holiday_weeks(): void
    {
        $this->mondayLesson([
            'title' => 'Turnen',
            'interval' => 2,
            'cadence' => 'school_weeks',
            'anchor_on' => '2027-01-04',
        ]);

        $this->semesterBreak();

        /*
         * The same rule, the same anchor, the same holidays — but the holiday
         * week does not count. After the holidays it continues where it left
         * off: 22 February is due, not 1 March. No lesson is dropped,
         * everything moves up.
         */
        $this->assertSame(
            ['2027-01-04', '2027-01-18', '2027-02-01', '2027-02-22', '2027-03-08'],
            $this->days('2027-01-04', '2027-03-08'),
        );
    }

    private function semesterBreak(): void
    {
        SchoolClosure::factory()->create([
            'household_id' => $this->household->id,
            'title' => 'Semesterferien',
            'starts_on' => '2027-02-15',
            'ends_on' => '2027-02-19',
        ]);
    }

    public function test_a_cancellation_is_delivered_struck_through(): void
    {
        $lesson = $this->mondayLesson();

        LessonChange::factory()->create([
            'household_id' => $this->household->id,
            'lesson_id' => $lesson->id,
            'date' => '2026-09-14',
        ]);

        $occurrences = $this->occurrences('2026-09-07', '2026-09-21');

        // Three Mondays, none of them gone.
        $this->assertCount(3, $occurrences);

        $cancelled = $occurrences->firstWhere(
            fn (LessonOccurrence $occurrence): bool => $occurrence->date->toDateString() === '2026-09-14',
        );

        $this->assertNotNull($cancelled);
        $this->assertTrue($cancelled->isCancelled());
        $this->assertSame('cancelled', $cancelled->status());
    }

    public function test_a_replacement_swaps_subject_and_time(): void
    {
        $lesson = $this->mondayLesson();

        LessonChange::factory()
            ->replacedBy('Turnen', '09:00', '10:30')
            ->create([
                'household_id' => $this->household->id,
                'lesson_id' => $lesson->id,
                'date' => '2026-09-14',
            ]);

        $occurrences = $this->occurrences('2026-09-07', '2026-09-21');
        $replaced = $occurrences->firstWhere(
            fn (LessonOccurrence $occurrence): bool => $occurrence->date->toDateString() === '2026-09-14',
        );

        $this->assertNotNull($replaced);
        $this->assertSame('Turnen', $replaced->title);
        $this->assertSame('Mathematik', $replaced->originalTitle);
        $this->assertSame('09:00', $replaced->startsAt->format('H:i'));
        $this->assertSame('replaced', $replaced->status());

        // The other Mondays stay untouched.
        $this->assertSame('Mathematik', $occurrences->first()->title);
        $this->assertNull($occurrences->first()->originalTitle);
    }

    /** A replacement is another subject; the lesson's colour would lie there. */
    public function test_the_colour_travels_with_the_subject(): void
    {
        $lesson = $this->mondayLesson(['color' => '#3b82f6']);

        LessonChange::factory()
            ->replacedBy('Turnen', '09:00', '10:30')
            ->create([
                'household_id' => $this->household->id,
                'lesson_id' => $lesson->id,
                'date' => '2026-09-14',
            ]);

        $this->assertSame(
            ['#3b82f6', null],
            $this->occurrences('2026-09-07', '2026-09-14')->map(fn (LessonOccurrence $occurrence): ?string => $occurrence->toArray()['color'])->all(),
        );
    }

    public function test_the_time_comes_from_the_bell_schedule(): void
    {
        LessonPeriod::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $this->lena->id,
            'number' => 3,
            'starts_at' => '09:50',
            'ends_at' => '10:40',
        ]);

        Lesson::factory()
            ->period(3)
            ->create([
                'household_id' => $this->household->id,
                'person_id' => $this->lena->id,
                'weekday' => 1,
                'anchor_on' => '2026-09-07',
            ]);

        $occurrence = $this->occurrences('2026-09-07', '2026-09-07')->first();

        $this->assertNotNull($occurrence);
        $this->assertSame('09:50', $occurrence->startsAt->format('H:i'));
        $this->assertSame('10:40', $occurrence->endsAt->format('H:i'));
        $this->assertSame(3, $occurrence->periodNumber);
    }

    /**
     * When the school week cadence shifts, a change can point at a day that
     * no longer has a lesson. It is then skipped — and not deleted, so it
     * applies again after a correction.
     */
    public function test_a_change_without_a_lesson_is_skipped(): void
    {
        $lesson = $this->mondayLesson();

        LessonChange::factory()->create([
            'household_id' => $this->household->id,
            'lesson_id' => $lesson->id,
            // A Wednesday — on which this lesson never takes place.
            'date' => '2026-09-16',
        ]);

        $this->assertSame(
            ['2026-09-07', '2026-09-14', '2026-09-21'],
            $this->days('2026-09-07', '2026-09-21'),
        );
    }

    public function test_an_inactive_lesson_does_not_appear(): void
    {
        $this->mondayLesson(['active' => false]);

        $this->assertSame([], $this->days('2026-09-07', '2026-09-21'));
    }

    /**
     * The database does not allow a period number and a time at once.
     * Otherwise the question would arise which of the two applies — and every
     * answer would be a guess.
     */
    public function test_period_and_time_together_are_rejected(): void
    {
        $this->expectException(QueryException::class);

        $this->mondayLesson(['period_number' => 1, 'starts_at' => '07:50', 'ends_at' => '08:40']);
    }

    public function test_a_lesson_without_any_time_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        $this->mondayLesson(['period_number' => null, 'starts_at' => null, 'ends_at' => null]);
    }
}
