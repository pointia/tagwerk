<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Models\Household;
use App\Models\Person;
use App\Models\Task;
use App\Models\User;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The recurrence editor: everyday fields in, RFC 5545 rule out — and back
 * into the same fields when editing.
 */
final class AdminTaskFormTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();
        $this->actingAs(User::factory()->create(['household_id' => $this->household->id]));

        // Livewire::test bypasses the middleware that would otherwise fill the context.
        app(HouseholdContext::class)->setId($this->household->id);
    }

    /** @param array<string, mixed> $fields */
    private function createTask(array $fields): ?Task
    {
        Livewire::test(CreateTask::class)
            ->fillForm(['title' => 'Müll rausbringen', 'due_on' => '2026-09-15', ...$fields])
            ->call('create')
            ->assertHasNoFormErrors();

        return Task::query()->where('title', 'Müll rausbringen')->first();
    }

    public function test_a_one_off_task_stores_no_rule(): void
    {
        $this->assertNull($this->createTask(['repeat' => 'once'])?->rrule);
    }

    public function test_a_task_for_every_child_morning_and_evening(): void
    {
        $task = $this->createTask(['repeat' => 'DAILY', 'per_person' => true, 'dayparts' => ['evening', 'morning']]);

        $this->assertTrue($task?->per_person);
        $this->assertNull($task?->per_person_ids);
        $this->assertSame(['morning', 'evening'], array_map(fn ($part): string => $part->value, $task?->dayparts() ?? []));
    }

    public function test_a_task_per_person_for_chosen_people(): void
    {
        $max = Person::factory()->create(['household_id' => $this->household->id]);

        $task = $this->createTask(['repeat' => 'DAILY', 'per_person' => true, 'per_person_ids' => [(string) $max->id]]);

        $this->assertSame([$max->id], $task?->per_person_ids);
    }

    public function test_weekdays_become_the_rule(): void
    {
        $task = $this->createTask(['repeat' => 'WEEKLY', 'interval' => 2, 'weekdays' => ['TH', 'MO']]);

        $this->assertSame('FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,TH', $task?->rrule);
        $this->assertSame($this->household->id, $task?->household_id);
    }

    public function test_the_nth_weekday_of_the_month_becomes_the_rule(): void
    {
        $task = $this->createTask([
            'repeat' => 'MONTHLY',
            'month_mode' => 'weekday',
            'month_week' => -1,
            'month_weekday' => 'FR',
        ]);

        $this->assertSame('FREQ=MONTHLY;BYDAY=-1FR', $task?->rrule);
    }

    public function test_an_invalid_custom_rule_is_rejected(): void
    {
        Livewire::test(CreateTask::class)
            ->fillForm([
                'title' => 'Müll rausbringen',
                'due_on' => '2026-09-15',
                'repeat' => 'custom',
                'custom_rule' => "FREQ=DAILY\nEXDATE:20260916",
            ])
            ->call('create')
            ->assertHasFormErrors(['custom_rule']);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_editing_spreads_the_rule_over_the_fields(): void
    {
        $task = Task::factory()->create([
            'household_id' => $this->household->id,
            'due_on' => '2026-09-10',
            'rrule' => 'FREQ=MONTHLY;BYDAY=+2TH',
        ]);

        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->assertFormSet([
                'repeat' => 'MONTHLY',
                'month_mode' => 'weekday',
                'month_week' => 2,
                'month_weekday' => 'TH',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('FREQ=MONTHLY;BYDAY=2TH', $task->refresh()->rrule);
    }

    public function test_a_foreign_rule_survives_saving(): void
    {
        $task = Task::factory()->create([
            'household_id' => $this->household->id,
            'rrule' => 'FREQ=DAILY;COUNT=5',
        ]);

        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->assertFormSet(['repeat' => 'custom', 'custom_rule' => 'FREQ=DAILY;COUNT=5'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('FREQ=DAILY;COUNT=5', $task->refresh()->rrule);
    }

    public function test_the_list_describes_the_recurrence(): void
    {
        Task::factory()->create([
            'household_id' => $this->household->id,
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO,TH',
        ]);

        $this->get('/admin/tasks')
            ->assertSuccessful()
            ->assertSee('Jeden Montag und Donnerstag')
            ->assertDontSee('BYDAY');
    }
}
