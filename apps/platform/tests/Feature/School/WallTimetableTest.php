<?php

declare(strict_types=1);

namespace Tests\Feature\School;

use App\Enums\Feature;
use App\Models\Household;
use App\Models\Lesson;
use App\Models\LessonChange;
use App\Models\Person;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class WallTimetableTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private Person $lena;

    private string $token;

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

        $device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        $this->token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];
    }

    /** @return TestResponse */
    private function fetch(string $from, string $to)
    {
        return $this->withToken($this->token)
            ->getJson("/api/wall/timetable?from={$from}&to={$to}");
    }

    public function test_no_access_without_token(): void
    {
        $this->getJson('/api/wall/timetable')->assertUnauthorized();
    }

    public function test_the_week_comes_with_children_and_bell_schedule(): void
    {
        Lesson::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $this->lena->id,
            'title' => 'Mathematik',
            'weekday' => 1,
            'anchor_on' => '2026-09-07',
        ]);

        $response = $this->fetch('2026-09-07', '2026-09-13')->assertOk();

        $response->assertJsonPath('lessons.0.title', 'Mathematik');
        $response->assertJsonPath('lessons.0.date', '2026-09-07');
        $response->assertJsonPath('lessons.0.status', 'normal');
        $response->assertJsonPath('people.0.name', 'Lena');
    }

    public function test_a_child_without_timetable_is_not_in_the_switcher(): void
    {
        Person::factory()->create([
            'household_id' => $this->household->id,
            'name' => 'Baby Tom',
        ]);

        Lesson::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $this->lena->id,
            'weekday' => 1,
            'anchor_on' => '2026-09-07',
        ]);

        $this->fetch('2026-09-07', '2026-09-13')
            ->assertOk()
            ->assertJsonCount(1, 'people')
            ->assertJsonPath('people.0.name', 'Lena');
    }

    public function test_a_lesson_can_be_cancelled(): void
    {
        $lesson = Lesson::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $this->lena->id,
            'weekday' => 1,
            'anchor_on' => '2026-09-07',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/wall/timetable/{$lesson->id}/change", [
                'date' => '2026-09-07',
                'kind' => 'cancelled',
            ])
            ->assertCreated();

        // Struck through, but not gone — otherwise nobody would see that the
        // day runs differently.
        $this->fetch('2026-09-07', '2026-09-07')
            ->assertOk()
            ->assertJsonCount(1, 'lessons')
            ->assertJsonPath('lessons.0.status', 'cancelled');
    }

    public function test_a_cancellation_discards_subject_and_time(): void
    {
        $lesson = Lesson::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $this->lena->id,
            'weekday' => 1,
            'anchor_on' => '2026-09-07',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/wall/timetable/{$lesson->id}/change", [
                'date' => '2026-09-07',
                'kind' => 'cancelled',
                'title' => 'Turnen',
                'starts_at' => '09:00',
            ])
            ->assertCreated();

        $change = LessonChange::query()->firstOrFail();

        $this->assertNull($change->title);
        $this->assertNull($change->starts_at);
    }

    public function test_a_replacement_appears_with_the_original_subject(): void
    {
        $lesson = Lesson::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $this->lena->id,
            'title' => 'Mathematik',
            'weekday' => 1,
            'anchor_on' => '2026-09-07',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/wall/timetable/{$lesson->id}/change", [
                'date' => '2026-09-07',
                'kind' => 'replaced',
                'title' => 'Turnen',
                'starts_at' => '09:00',
                'ends_at' => '10:30',
            ])
            ->assertCreated();

        $this->fetch('2026-09-07', '2026-09-07')
            ->assertOk()
            ->assertJsonPath('lessons.0.title', 'Turnen')
            ->assertJsonPath('lessons.0.original_title', 'Mathematik')
            ->assertJsonPath('lessons.0.status', 'replaced');
    }

    public function test_cancelling_twice_stays_one_change(): void
    {
        $lesson = Lesson::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $this->lena->id,
            'weekday' => 1,
            'anchor_on' => '2026-09-07',
        ]);

        foreach (['cancelled', 'replaced'] as $kind) {
            $this->withToken($this->token)
                ->postJson("/api/wall/timetable/{$lesson->id}/change", [
                    'date' => '2026-09-07',
                    'kind' => $kind,
                    'title' => 'Turnen',
                ])
                ->assertCreated();
        }

        $this->assertSame(1, LessonChange::query()->count());
        $this->assertSame('replaced', LessonChange::query()->firstOrFail()->kind->value);
    }

    public function test_a_change_can_be_reverted(): void
    {
        $lesson = Lesson::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $this->lena->id,
            'weekday' => 1,
            'anchor_on' => '2026-09-07',
        ]);

        LessonChange::factory()->create([
            'household_id' => $this->household->id,
            'lesson_id' => $lesson->id,
            'date' => '2026-09-07',
        ]);

        $this->withToken($this->token)
            ->deleteJson("/api/wall/timetable/{$lesson->id}/change", ['date' => '2026-09-07'])
            ->assertNoContent();

        $this->fetch('2026-09-07', '2026-09-07')
            ->assertOk()
            ->assertJsonPath('lessons.0.status', 'normal');
    }

    public function test_the_agenda_includes_the_lessons_for_the_calendar_band(): void
    {
        Lesson::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $this->lena->id,
            'title' => 'Mathematik',
            'weekday' => 1,
            'anchor_on' => '2026-09-07',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/wall/agenda?from=2026-09-07&to=2026-09-13')
            ->assertOk()
            ->assertJsonPath('lessons.0.title', 'Mathematik')
            ->assertJsonPath('lessons.0.date', '2026-09-07');
    }

    public function test_without_timetable_the_calendar_band_stays_empty(): void
    {
        $this->household->update(['disabled_features' => [Feature::Timetable->value]]);

        Lesson::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $this->lena->id,
            'weekday' => 1,
            'anchor_on' => '2026-09-07',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/wall/agenda?from=2026-09-07&to=2026-09-13')
            ->assertOk()
            ->assertJsonCount(0, 'lessons');
    }

    public function test_a_long_range_stays_without_lessons(): void
    {
        // A year-long fetch would otherwise pull thousands of lessons no band shows.
        Lesson::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $this->lena->id,
            'weekday' => 1,
            'anchor_on' => '2026-09-07',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/wall/agenda?from=2026-09-07&to=2027-06-30')
            ->assertOk()
            ->assertJsonCount(0, 'lessons');
    }

    public function test_a_disabled_timetable_has_no_routes(): void
    {
        $this->household->update(['disabled_features' => [Feature::Timetable->value]]);

        $this->withToken($this->token)
            ->getJson('/api/wall/timetable')
            ->assertNotFound();
    }
}
