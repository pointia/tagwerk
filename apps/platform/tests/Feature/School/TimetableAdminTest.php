<?php

declare(strict_types=1);

namespace Tests\Feature\School;

use App\Enums\Feature;
use App\Enums\ProviderArea;
use App\Filament\Resources\Lessons\Pages\CreateLesson;
use App\Filament\Resources\People\Pages\EditPerson;
use App\Filament\Resources\SchoolClosures\Pages\ListSchoolClosures;
use App\Models\Household;
use App\Models\Lesson;
use App\Models\LessonPeriod;
use App\Models\Person;
use App\Models\ProviderAccount;
use App\Models\SchoolClosure;
use App\Models\User;
use App\Support\HouseholdContext;
use App\Support\PublicAddressResolver;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\FakeResolver;
use Tests\TestCase;

final class TimetableAdminTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private Person $lena;

    protected function setUp(): void
    {
        parent::setUp();

        // A public address, so the check for internal targets lets it through.
        $this->app->instance(PublicAddressResolver::class, new FakeResolver(['ferien.example.test' => ['93.184.216.34']]));

        $this->household = Household::factory()->create(['holiday_region' => 'AT-4']);
        $this->actingAs(User::factory()->create(['household_id' => $this->household->id]));
        app(HouseholdContext::class)->setId((int) $this->household->id);

        $this->lena = Person::factory()->child()->create([
            'household_id' => $this->household->id,
            'name' => 'Lena',
        ]);
    }

    public function test_the_timetable_list_shows_subject_and_cadence(): void
    {
        Lesson::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $this->lena->id,
            'title' => 'Bewegung und Sport',
            'interval' => 2,
            'cadence' => 'school_weeks',
        ]);

        $this->get('/admin/lessons')
            ->assertSuccessful()
            ->assertSee('Bewegung und Sport')
            ->assertSee('Lena')
            ->assertSee('jede 2. Woche');
    }

    /**
     * The database does not allow period number and time at once. The form
     * therefore has to clean up before saving — otherwise the old value would
     * remain after switching.
     */
    /** School means nothing for a parent: no bell schedule, no lessons. */
    public function test_the_timetable_is_offered_for_children_only(): void
    {
        $mama = Person::factory()->for($this->household)->create(['name' => 'Mama']);

        Livewire::test(EditPerson::class, ['record' => $mama->getRouteKey()])
            ->assertFormFieldHidden('lessonPeriods');

        Livewire::test(EditPerson::class, ['record' => $this->lena->getRouteKey()])
            ->assertFormFieldVisible('lessonPeriods');

        Livewire::test(CreateLesson::class)
            ->assertFormFieldExists('person_id', fn (Select $field): bool => array_keys($field->getOptions()) === [$this->lena->id]);
    }

    public function test_a_lesson_from_the_bell_schedule_stores_no_time(): void
    {
        $this->bellSchedule();

        Livewire::test(CreateLesson::class)
            ->fillForm([
                'person_id' => $this->lena->id,
                'title' => 'Deutsch',
                'time_source' => 'period',
                'slots' => ['2-2'],
                'interval' => 1,
                'anchor_on' => '2026-09-07',
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $lesson = Lesson::query()->firstOrFail();

        $this->assertSame(2, $lesson->weekday);
        $this->assertSame(2, $lesson->period_number);
        $this->assertNull($lesson->starts_at);
        $this->assertNull($lesson->ends_at);
    }

    /** One subject, every slot it takes in the week, in one go. */
    public function test_the_week_grid_creates_one_lesson_per_chosen_slot(): void
    {
        $this->bellSchedule();

        Livewire::test(CreateLesson::class)
            ->fillForm([
                'person_id' => $this->lena->id,
                'title' => 'GU',
                'time_source' => 'period',
                'slots' => ['1-1', '1-2', '3-1', '5-2'],
                'interval' => 1,
                'anchor_on' => '2026-09-07',
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin/lessons');

        $this->assertSame(
            ['1-1', '1-2', '3-1', '5-2'],
            Lesson::query()->where('title', 'GU')->orderBy('weekday')->orderBy('period_number')->get()
                ->map(fn (Lesson $lesson): string => "{$lesson->weekday}-{$lesson->period_number}")->all(),
        );
    }

    public function test_a_taken_slot_shows_greyed_and_is_refused(): void
    {
        $this->bellSchedule();
        Lesson::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $this->lena->id,
            'title' => 'Religion',
            'weekday' => 1,
            'period_number' => 1,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        Livewire::test(CreateLesson::class)
            ->fillForm(['person_id' => $this->lena->id])
            ->assertSeeHtml('data-slot="1-1"')
            ->assertSee('Religion')
            ->fillForm([
                'title' => 'GU',
                'time_source' => 'period',
                'slots' => ['1-1', '1-2'],
                'interval' => 1,
                'anchor_on' => '2026-09-07',
                'active' => true,
            ])
            ->call('create')
            ->assertHasErrors(['data.slots']);

        $this->assertSame(0, Lesson::query()->where('title', 'GU')->count());
    }

    public function test_create_another_keeps_the_child(): void
    {
        $this->bellSchedule();

        Livewire::test(CreateLesson::class)
            ->fillForm([
                'person_id' => $this->lena->id,
                'title' => 'GU',
                'time_source' => 'period',
                'slots' => ['1-1'],
                'interval' => 1,
                'anchor_on' => '2026-09-07',
                'active' => true,
            ])
            ->call('createAnother')
            ->assertHasNoFormErrors()
            ->assertFormSet(['person_id' => $this->lena->id, 'title' => null]);
    }

    public function test_the_grid_stores_the_chosen_colour(): void
    {
        $this->bellSchedule();

        Livewire::test(CreateLesson::class)
            ->fillForm([
                'person_id' => $this->lena->id,
                'title' => 'GU',
                'color' => '#22c55e',
                'time_source' => 'period',
                'slots' => ['1-1', '2-1'],
                'interval' => 1,
                'anchor_on' => '2026-09-07',
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['#22c55e', '#22c55e'], Lesson::query()->pluck('color')->all());
    }

    public function test_a_colour_that_is_no_hex_value_is_refused(): void
    {
        $this->bellSchedule();

        Livewire::test(CreateLesson::class)
            ->fillForm([
                'person_id' => $this->lena->id,
                'title' => 'GU',
                'color' => 'red; background: url(x)',
                'time_source' => 'period',
                'slots' => ['1-1'],
                'interval' => 1,
                'anchor_on' => '2026-09-07',
                'active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['color']);
    }

    /** A subject the child already has comes with its colour. */
    public function test_a_known_subject_brings_its_colour(): void
    {
        $this->lesson('Mathematik', 1, '#3b82f6');

        Livewire::test(CreateLesson::class)
            ->fillForm(['person_id' => $this->lena->id, 'title' => 'Mathematik'])
            ->assertFormSet(['color' => '#3b82f6']);
    }

    /** The colour belongs to the subject: one change recolours all its lessons. */
    public function test_a_new_colour_reaches_every_lesson_of_the_subject(): void
    {
        $first = $this->lesson('Mathematik', 1, '#3b82f6');
        $this->lesson('Mathematik', 3, '#3b82f6');
        $other = $this->lesson('Deutsch', 2, '#ef4444');
        $sibling = Person::factory()->child()->create(['household_id' => $this->household->id]);
        $foreign = $this->lesson('Mathematik', 4, '#3b82f6', $sibling);

        $first->update(['color' => '#ec4899']);

        $this->assertSame(2, Lesson::query()->where('title', 'Mathematik')->where('color', '#ec4899')->count());
        $this->assertSame('#ef4444', $other->fresh()->color);
        $this->assertSame('#3b82f6', $foreign->fresh()->color);
    }

    public function test_a_new_lesson_without_colour_leaves_the_subject_alone(): void
    {
        $this->lesson('Mathematik', 1, '#3b82f6');
        $this->lesson('Mathematik', 2, null);

        $this->assertSame(1, Lesson::query()->where('color', '#3b82f6')->count());
    }

    private function lesson(string $title, int $weekday, ?string $color, ?Person $person = null): Lesson
    {
        return Lesson::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => ($person ?? $this->lena)->id,
            'title' => $title,
            'weekday' => $weekday,
            'color' => $color,
        ]);
    }

    private function bellSchedule(): void
    {
        foreach ([1 => ['08:00', '08:50'], 2 => ['08:45', '09:35']] as $number => [$from, $to]) {
            LessonPeriod::factory()->create([
                'household_id' => $this->household->id,
                'person_id' => $this->lena->id,
                'number' => $number,
                'starts_at' => $from,
                'ends_at' => $to,
            ]);
        }
    }

    public function test_a_lesson_with_custom_time_stores_no_period_number(): void
    {
        Livewire::test(CreateLesson::class)
            ->fillForm([
                'person_id' => $this->lena->id,
                'title' => 'Musikschule',
                'weekday' => 3,
                'time_source' => 'custom',
                'starts_at' => '16:30',
                'ends_at' => '17:15',
                'interval' => 2,
                'cadence' => 'school_weeks',
                'anchor_on' => '2026-09-09',
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $lesson = Lesson::query()->firstOrFail();

        $this->assertNull($lesson->period_number);
        $this->assertSame('16:30:00', $lesson->starts_at);
        $this->assertSame(2, $lesson->interval);
    }

    public function test_the_bell_schedule_is_maintained_on_the_child(): void
    {
        Livewire::test(EditPerson::class, ['record' => $this->lena->getRouteKey()])
            ->fillForm([
                'lessonPeriods' => [
                    ['number' => 1, 'starts_at' => '07:50', 'ends_at' => '08:40'],
                    ['number' => 2, 'starts_at' => '08:45', 'ends_at' => '09:35'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $periods = $this->lena->fresh()->lessonPeriods;

        $this->assertCount(2, $periods);
        $this->assertSame('07:50:00', $periods->first()->starts_at);

        // The household comes from the context, not from the form.
        $this->assertSame($this->household->id, $periods->first()->household_id);
    }

    public function test_the_suggestion_creates_the_ticked_holidays(): void
    {
        Livewire::test(ListSchoolClosures::class)
            ->callAction('suggestHolidays', [
                'school_year' => 2026,
                'holidays' => ['2026-10-27_2026-10-31', '2026-12-24_2027-01-06'],
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(2, SchoolClosure::query()->count());

        $autumn = SchoolClosure::query()->where('title', 'Herbstferien')->firstOrFail();

        $this->assertSame('2026-10-27', $autumn->starts_on->toDateString());
        $this->assertSame('2026-10-31', $autumn->ends_on->toDateString());
        $this->assertNull($autumn->person_id);
    }

    /**
     * A household outside Austria gets suggestions from its connected holiday
     * calendar — the builtin rule does not know it.
     */
    public function test_the_suggestion_uses_a_connected_holiday_calendar(): void
    {
        $this->household->update(['holiday_region' => 'DE-BY']);

        ProviderAccount::create([
            'area' => ProviderArea::SchoolHolidays,
            'provider' => 'ics',
            'account_label' => 'bayern.ics',
            'credentials' => ['url' => 'https://ferien.example.test/bayern.ics'],
        ]);

        Http::fake(['ferien.example.test/*' => Http::response(implode("\r\n", [
            'BEGIN:VCALENDAR',
            'BEGIN:VEVENT',
            'SUMMARY:Herbstferien Bayern',
            'DTSTART;VALUE=DATE:20261102',
            'DTEND;VALUE=DATE:20261107',
            'END:VEVENT',
            'END:VCALENDAR',
        ]))]);

        Livewire::test(ListSchoolClosures::class)
            ->assertActionVisible('suggestHolidays')
            ->callAction('suggestHolidays', [
                'school_year' => 2026,
                'holidays' => ['2026-11-02_2026-11-06'],
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('Herbstferien Bayern', SchoolClosure::query()->value('title'));
    }

    public function test_without_a_matching_source_there_is_no_suggestion(): void
    {
        $this->household->update(['holiday_region' => 'DE-BY']);

        Livewire::test(ListSchoolClosures::class)->assertActionHidden('suggestHolidays');
    }

    /**
     * The suggestion must not overwrite anything: whoever corrected their
     * school's semester break by hand finds it unchanged the next time.
     */
    public function test_the_suggestion_does_not_touch_existing_entries(): void
    {
        $own = SchoolClosure::factory()->create([
            'household_id' => $this->household->id,
            'title' => 'Herbstferien',
            'starts_on' => '2026-10-26',
            'ends_on' => '2026-11-02',
        ]);

        Livewire::test(ListSchoolClosures::class)
            ->callAction('suggestHolidays', [
                'school_year' => 2026,
                'holidays' => ['2026-10-27_2026-10-31'],
            ]);

        $this->assertSame('2026-10-26', $own->fresh()->starts_on->toDateString());
        $this->assertSame(2, SchoolClosure::query()->count());
    }

    /**
     * In the admin a disabled area blocks with 403, not with 404 as in the
     * API. The difference is intended: a signed-in parent knows the area
     * exists and should learn that it is disabled. A paired wall tablet
     * should not learn that.
     */
    public function test_a_disabled_timetable_blocks_the_admin(): void
    {
        $this->household->update(['disabled_features' => [Feature::Timetable->value]]);

        $this->get('/admin/lessons')->assertForbidden();
        $this->get('/admin/school-closures')->assertForbidden();
    }
}
