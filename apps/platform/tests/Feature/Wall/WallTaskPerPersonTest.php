<?php

declare(strict_types=1);

namespace Tests\Feature\Wall;

use App\Models\Household;
use App\Models\Person;
use App\Models\PointEntry;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Zähne putzen" for every child, morning and evening: one task, and every
 * child ticks each round for themselves.
 */
final class WallTaskPerPersonTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private string $token;

    private Person $lena;

    private Person $max;

    protected function setUp(): void
    {
        parent::setUp();

        // A past day can no longer be ticked: the clock stands on the first day the tests tick.
        $this->travelTo(CarbonImmutable::parse('2026-09-28 08:00', 'Europe/Vienna'));

        $this->household = Household::factory()->create(['timezone' => 'Europe/Vienna']);
        app(HouseholdContext::class)->setId((int) $this->household->id);

        $device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        $this->token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];

        $this->lena = Person::factory()->child()->create(['household_id' => $this->household->id, 'name' => 'Lena']);
        $this->max = Person::factory()->child()->create(['household_id' => $this->household->id, 'name' => 'Max']);
        Person::factory()->create(['household_id' => $this->household->id, 'name' => 'Papa']);
    }

    private function teeth(array $overrides = []): Task
    {
        return Task::factory()->create([
            'household_id' => $this->household->id,
            'title' => 'Zähne putzen',
            'rrule' => 'FREQ=DAILY',
            'due_on' => '2026-09-28',
            'points' => 1,
            'per_person' => true,
            'dayparts' => ['evening', 'morning'],
            ...$overrides,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function day(): array
    {
        return $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from=2026-09-29&to=2026-09-29')
            ->assertOk()
            ->json('occurrences');
    }

    public function test_every_child_gets_each_time_of_day(): void
    {
        $this->teeth();

        $this->assertSame(
            ['morning Lena', 'morning Max', 'evening Lena', 'evening Max'],
            array_map(fn (array $entry): string => "{$entry['daypart']} {$entry['person_name']}", $this->day()),
        );
    }

    /** No choice means every child, so a child added later has it at once. */
    public function test_a_new_child_gets_the_task_without_editing_it(): void
    {
        $this->teeth(['dayparts' => null]);
        Person::factory()->child()->create(['household_id' => $this->household->id, 'name' => 'Anna']);

        $this->assertSame(['Anna', 'Lena', 'Max'], array_column($this->day(), 'person_name'));
    }

    public function test_a_choice_limits_the_task_to_those_people(): void
    {
        $this->teeth(['dayparts' => null, 'per_person_ids' => [$this->max->id]]);

        $this->assertSame(['Max'], array_column($this->day(), 'person_name'));
    }

    public function test_each_child_ticks_and_earns_for_themselves(): void
    {
        $task = $this->teeth();

        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-29', 'for_person_id' => $this->lena->id, 'daypart' => 'morning'])
            ->assertCreated()
            ->assertJsonPath('points', 1);

        $done = array_map(fn (array $entry): string => "{$entry['daypart']} {$entry['person_name']} ".($entry['done'] ? 'x' : '-'), $this->day());
        $this->assertSame(['morning Lena x', 'morning Max -', 'evening Lena -', 'evening Max -'], $done);

        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-29', 'for_person_id' => $this->max->id, 'daypart' => 'morning'])
            ->assertCreated();
        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-29', 'for_person_id' => $this->lena->id, 'daypart' => 'evening'])
            ->assertCreated();

        $this->assertSame(2, (int) PointEntry::query()->where('person_id', $this->lena->id)->sum('points'));
        $this->assertSame(1, (int) PointEntry::query()->where('person_id', $this->max->id)->sum('points'));
    }

    public function test_a_second_tap_earns_nothing_more(): void
    {
        $task = $this->teeth();
        $body = ['date' => '2026-09-29', 'for_person_id' => $this->lena->id, 'daypart' => 'morning'];

        $this->withToken($this->token)->postJson("/api/wall/tasks/{$task->id}/completion", $body)->assertCreated();
        $this->withToken($this->token)->postJson("/api/wall/tasks/{$task->id}/completion", $body)->assertCreated()->assertJsonPath('points', 0);

        $this->assertSame(1, TaskCompletion::query()->count());
    }

    public function test_taking_back_touches_only_that_occurrence(): void
    {
        $task = $this->teeth();

        foreach (['morning', 'evening'] as $daypart) {
            $this->withToken($this->token)
                ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-29', 'for_person_id' => $this->lena->id, 'daypart' => $daypart])
                ->assertCreated();
        }

        $this->withToken($this->token)
            ->deleteJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-29', 'for_person_id' => $this->lena->id, 'daypart' => 'morning'])
            ->assertOk();

        $this->assertSame(['evening'], TaskCompletion::query()->pluck('daypart')->all());
    }

    public function test_the_occurrence_must_exist_on_the_task(): void
    {
        $task = $this->teeth(['dayparts' => ['morning']]);

        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-29', 'daypart' => 'morning'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('for_person_id');

        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-29', 'for_person_id' => $this->lena->id, 'daypart' => 'evening'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('daypart');
    }

    /** An ordinary task stays at one completion a day, whatever is sent along. */
    public function test_an_ordinary_task_ignores_the_extra_fields(): void
    {
        $task = Task::factory()->create([
            'household_id' => $this->household->id,
            'rrule' => 'FREQ=DAILY',
            'due_on' => '2026-09-28',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-29', 'for_person_id' => $this->lena->id, 'daypart' => 'noon'])
            ->assertCreated();
        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-29'])
            ->assertCreated();

        $this->assertSame(1, TaskCompletion::query()->count());
        $this->assertNull(TaskCompletion::query()->value('for_person_id'));
    }
}
