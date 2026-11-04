<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Household;
use App\Models\Person;
use App\Models\SchoolClosure;
use App\Models\Task;
use App\Services\TaskOccurrenceService;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Homework pauses in the holidays that are entered under School closures —
 * for every child, or only for the one whose school is off.
 */
final class TaskHolidaysTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private Person $max;

    private Person $lena;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();
        app(HouseholdContext::class)->setId((int) $this->household->id);

        $this->max = Person::factory()->child()->create(['household_id' => $this->household->id, 'name' => 'Max']);
        $this->lena = Person::factory()->child()->create(['household_id' => $this->household->id, 'name' => 'Lena']);
    }

    public function test_a_closure_for_all_children_skips_the_days(): void
    {
        $this->homework(skip: true);
        $this->closure(null, '2026-10-27', '2026-10-31');

        // Monday 26th to Wednesday 28th: only Monday remains, for both.
        $this->assertSame(['2026-10-26 Lena', '2026-10-26 Max'], $this->occurrences());
    }

    public function test_a_closure_for_one_child_only_pauses_that_child(): void
    {
        $this->homework(skip: true);
        $this->closure($this->max, '2026-10-27', '2026-10-28');

        $this->assertSame(
            ['2026-10-26 Lena', '2026-10-26 Max', '2026-10-27 Lena', '2026-10-28 Lena'],
            $this->occurrences(),
        );
    }

    public function test_without_the_switch_holidays_change_nothing(): void
    {
        $this->homework(skip: false);
        $this->closure(null, '2026-10-27', '2026-10-31');

        $this->assertCount(6, $this->occurrences());
    }

    public function test_an_ordinary_task_follows_the_closures_of_its_person(): void
    {
        Task::factory()->create([
            'household_id' => $this->household->id,
            'title' => 'Lesen',
            'person_id' => $this->lena->id,
            'rrule' => 'FREQ=DAILY',
            'due_on' => '2026-10-01',
            'skip_holidays' => true,
        ]);
        $this->closure($this->max, '2026-10-26', '2026-10-28');
        $this->closure($this->lena, '2026-10-27', '2026-10-27');

        $this->assertSame(['2026-10-26 Lena', '2026-10-28 Lena'], $this->occurrences());
    }

    private function homework(bool $skip): void
    {
        Task::factory()->create([
            'household_id' => $this->household->id,
            'title' => 'Hausaufgaben',
            'person_id' => null,
            'per_person' => true,
            'per_person_ids' => [$this->max->id, $this->lena->id],
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR',
            'due_on' => '2026-10-01',
            'skip_holidays' => $skip,
        ]);
    }

    private function closure(?Person $person, string $from, string $to): void
    {
        SchoolClosure::query()->create([
            'household_id' => $this->household->id,
            'person_id' => $person?->id,
            'title' => 'Herbstferien',
            'starts_on' => $from,
            'ends_on' => $to,
        ]);
    }

    /** @return array<int, string> */
    private function occurrences(): array
    {
        $found = array_map(
            fn (array $occurrence): string => $occurrence['date']->toDateString().' '.$occurrence['person']?->name,
            app(TaskOccurrenceService::class)->between(CarbonImmutable::parse('2026-10-26'), CarbonImmutable::parse('2026-10-28')),
        );
        sort($found);

        return $found;
    }
}
