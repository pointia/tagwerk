<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Household;
use App\Models\Person;
use App\Models\Task;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Whose faces the admin's task list shows under "Assigned to" — always in the
 * family's order, children by age first.
 */
final class TaskResponsibleTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private Person $lena;

    private Person $max;

    private Person $papa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();
        app(HouseholdContext::class)->setId((int) $this->household->id);

        // Created out of order on purpose: the order must come from age.
        $this->max = Person::factory()->child()->create(['household_id' => $this->household->id, 'name' => 'Max', 'birthday' => '2018-05-01']);
        $this->papa = Person::factory()->create(['household_id' => $this->household->id, 'name' => 'Papa']);
        $this->lena = Person::factory()->child()->create(['household_id' => $this->household->id, 'name' => 'Lena', 'birthday' => '2015-03-01']);
    }

    public function test_one_person(): void
    {
        $task = Task::factory()->create(['household_id' => $this->household->id, 'person_id' => $this->max->id]);

        $this->assertSame(['Max'], $this->names($task));
    }

    public function test_every_child_in_order_when_none_is_chosen(): void
    {
        $task = Task::factory()->create(['household_id' => $this->household->id, 'per_person' => true, 'per_person_ids' => []]);

        $this->assertSame(['Lena', 'Max'], $this->names($task));
    }

    public function test_chosen_people_of_a_task_per_person_in_family_order(): void
    {
        $task = Task::factory()->create(['household_id' => $this->household->id, 'per_person' => true, 'per_person_ids' => [$this->papa->id, $this->max->id]]);

        $this->assertSame(['Max', 'Papa'], $this->names($task));
    }

    public function test_a_rotation_keeps_its_turn_order(): void
    {
        $task = Task::factory()->create(['household_id' => $this->household->id, 'rotation' => [$this->max->id, $this->lena->id]]);

        $this->assertSame(['Max', 'Lena'], $this->names($task));
    }

    public function test_everyone_is_the_whole_household(): void
    {
        $task = Task::factory()->create(['household_id' => $this->household->id, 'person_id' => null]);

        $this->assertSame(['Lena', 'Max', 'Papa'], $this->names($task));
    }

    public function test_a_face_without_picture_is_the_initial(): void
    {
        $face = base64_decode(substr($this->lena->face(), strlen('data:image/svg+xml;base64,')));

        $this->assertStringContainsString('>L</text>', $face);
    }

    /** @return array<int, string> */
    private function names(Task $task): array
    {
        return $task->responsible(Person::ordered()->get())->pluck('name')->all();
    }
}
