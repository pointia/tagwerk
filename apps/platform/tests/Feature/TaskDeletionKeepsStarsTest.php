<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Models\Household;
use App\Models\Person;
use App\Models\PointEntry;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\User;
use App\Services\PointLedger;
use App\Support\HouseholdContext;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Earned stars survive removing a task.
 *
 * A task with history is soft-deleted: it is gone from admin, wall and phone,
 * while its completions and stars stay. The database also refuses the hard
 * delete so that no other path takes the entries along through the cascade.
 */
final class TaskDeletionKeepsStarsTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();
        $this->actingAs(User::factory()->create(['household_id' => $this->household->id]));

        app(HouseholdContext::class)->setId($this->household->id);
    }

    private function completedTask(): Task
    {
        $child = Person::factory()->create(['household_id' => $this->household->id]);
        $task = Task::factory()->create([
            'household_id' => $this->household->id,
            'points' => 5,
            'due_on' => '2026-09-16',
        ]);

        $completion = TaskCompletion::create([
            'task_id' => $task->id,
            'person_id' => $child->id,
            'occurred_on' => '2026-09-16',
            'completed_at' => now(),
        ]);

        app(PointLedger::class)->awardForCompletion($completion);

        return $task;
    }

    public function test_deleting_in_the_admin_removes_a_task_with_history_but_keeps_its_stars(): void
    {
        $task = $this->completedTask();

        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertSoftDeleted($task);
        $this->assertSame(1, TaskCompletion::query()->count());
        $this->assertSame(5, (int) PointEntry::query()->sum('points'));

        Livewire::test(ListTasks::class)->assertCanNotSeeTableRecords([$task]);
    }

    public function test_bulk_delete_removes_tasks_with_history_but_keeps_their_stars(): void
    {
        $task = $this->completedTask();

        Livewire::test(ListTasks::class)
            ->callTableBulkAction('delete', [$task]);

        $this->assertSoftDeleted($task);
        $this->assertSame(5, (int) PointEntry::query()->sum('points'));
    }

    public function test_a_deleted_task_can_be_restored(): void
    {
        $task = $this->completedTask();
        $task->retire();

        Livewire::test(ListTasks::class)
            ->filterTable('trashed', false)
            ->assertCanSeeTableRecords([$task])
            ->callTableAction(RestoreAction::class, $task);

        $this->assertNotSoftDeleted($task);
    }

    public function test_the_history_still_names_a_deleted_task(): void
    {
        $task = $this->completedTask();
        $task->retire();

        $this->assertSame($task->title, TaskCompletion::query()->firstOrFail()->task?->title);
    }

    public function test_a_task_without_history_is_really_deleted(): void
    {
        $task = Task::factory()->create(['household_id' => $this->household->id]);

        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertNull(Task::withTrashed()->find($task->id));
    }

    public function test_the_database_refuses_the_hard_delete(): void
    {
        $task = $this->completedTask();

        $this->expectException(QueryException::class);

        $task->forceDelete();
    }

    public function test_a_household_can_still_be_deleted_entirely(): void
    {
        $this->completedTask();

        $this->household->delete();

        $this->assertSame(0, Task::withoutHouseholdScope()->withTrashed()->count());
    }
}
