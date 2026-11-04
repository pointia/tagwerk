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

final class WallTaskTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // A past day can no longer be ticked: the clock stands on the first day the tests tick.
        $this->travelTo(CarbonImmutable::parse('2026-09-14 08:00', 'Europe/Vienna'));

        $this->household = Household::factory()->create(['timezone' => 'Europe/Vienna']);

        app(HouseholdContext::class)->setId((int) $this->household->id);

        $device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        $this->token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];
    }

    /** @param array<string, mixed> $attributes */
    private function task(array $attributes = []): Task
    {
        return Task::factory()->create([
            'household_id' => $this->household->id,
            ...$attributes,
        ]);
    }

    public function test_no_access_without_token(): void
    {
        $this->getJson('/api/wall/tasks')->assertUnauthorized();
    }

    public function test_one_off_task_appears_on_its_day(): void
    {
        $this->task(['title' => 'Müll rausbringen', 'due_on' => '2026-09-16']);

        $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from=2026-09-14&to=2026-09-20')
            ->assertOk()
            ->assertJsonCount(1, 'occurrences')
            ->assertJsonPath('occurrences.0.title', 'Müll rausbringen')
            ->assertJsonPath('occurrences.0.date', '2026-09-16')
            ->assertJsonPath('occurrences.0.routine', false)
            ->assertJsonPath('occurrences.0.recurrence', null);
    }

    /**
     * A task "for everyone" credits whoever did it; the wall adds those
     * stars to that person's column. What counts is what was booked, not
     * what the task is worth today.
     */
    public function test_the_occurrence_says_who_earned_how_many_stars(): void
    {
        $child = Person::factory()->child()->create(['household_id' => $this->household->id]);
        $task = $this->task(['title' => 'Wäsche verräumen', 'due_on' => '2026-09-16', 'points' => 1, 'person_id' => null]);

        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-16', 'person_id' => $child->id])
            ->assertCreated();

        $task->update(['points' => 3]);

        $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from=2026-09-16&to=2026-09-16')
            ->assertJsonPath('occurrences.0.person_id', null)
            ->assertJsonPath('occurrences.0.done_by_id', $child->id)
            ->assertJsonPath('occurrences.0.earned', 1)
            ->assertJsonPath('occurrences.0.points', 3);
    }

    /**
     * Extra stars from the stars area count in the task columns too, on the
     * household's day — otherwise the column would not add up with the
     * balance. Credits for tasks are already in the occurrences.
     */
    public function test_extra_stars_come_per_person_and_household_day(): void
    {
        $child = Person::factory()->child()->create(['household_id' => $this->household->id]);
        $task = $this->task(['due_on' => '2026-09-16', 'points' => 2, 'person_id' => $child->id]);

        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-16'])
            ->assertCreated();

        // 00:30 and 23:30 in Vienna: both the 16th, though UTC says 15th for the first.
        foreach (['2026-09-15 22:30:00' => 3, '2026-09-16 21:30:00' => -1, '2026-09-16 22:30:00' => 5] as $at => $points) {
            PointEntry::query()->create(['person_id' => $child->id, 'points' => $points, 'reason' => 'Extra'])
                ->forceFill(['created_at' => CarbonImmutable::parse($at, 'UTC')])
                ->save();
        }

        $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from=2026-09-16&to=2026-09-16')
            ->assertOk()
            ->assertJsonPath('occurrences.0.earned', 2)
            ->assertJsonPath('bonus', [['person_id' => $child->id, 'date' => '2026-09-16', 'points' => 2, 'day_bonus' => false]]);
    }

    /**
     * The icon is for children who cannot read yet — without it the wall
     * would show only text.
     */
    public function test_the_task_icon_is_included(): void
    {
        $this->task(['title' => 'Zähne putzen', 'icon' => '🦷', 'due_on' => '2026-09-16']);
        $this->task(['title' => 'Müll rausbringen', 'icon' => null, 'due_on' => '2026-09-16']);

        $response = $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from=2026-09-14&to=2026-09-20')
            ->assertOk();

        $icons = array_column($response->json('occurrences'), 'icon', 'title');

        $this->assertSame('🦷', $icons['Zähne putzen']);
        $this->assertNull($icons['Müll rausbringen']);
    }

    public function test_task_outside_the_range_is_missing(): void
    {
        $this->task(['due_on' => '2026-10-30']);

        $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from=2026-09-14&to=2026-09-20')
            ->assertOk()
            ->assertJsonCount(0, 'occurrences');
    }

    public function test_routine_appears_on_every_date_of_the_rule(): void
    {
        $this->task([
            'title' => 'Zimmer aufräumen',
            'due_on' => '2026-09-14',
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO,WE',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from=2026-09-14&to=2026-09-25')
            ->assertOk();

        // The 14th and 21st are Mondays, the 16th and 23rd Wednesdays.
        $response->assertJsonCount(4, 'occurrences')
            ->assertJsonPath('occurrences.0.date', '2026-09-14')
            ->assertJsonPath('occurrences.1.date', '2026-09-16')
            ->assertJsonPath('occurrences.2.date', '2026-09-21')
            ->assertJsonPath('occurrences.3.date', '2026-09-23')
            ->assertJsonPath('occurrences.0.routine', true)
            ->assertJsonPath('occurrences.0.recurrence', 'Jeden Montag und Mittwoch');
    }

    public function test_inactive_task_does_not_appear(): void
    {
        $this->task(['due_on' => '2026-09-16', 'active' => false]);

        $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from=2026-09-14&to=2026-09-20')
            ->assertJsonCount(0, 'occurrences');
    }

    public function test_completing_applies_only_to_that_one_occurrence(): void
    {
        $task = $this->task([
            'due_on' => '2026-09-14',
            'rrule' => 'FREQ=DAILY',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-15'])
            ->assertCreated();

        $response = $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from=2026-09-14&to=2026-09-16')
            ->assertOk();

        $response->assertJsonPath('occurrences.0.done', false)
            ->assertJsonPath('occurrences.1.done', true)
            ->assertJsonPath('occurrences.2.done', false);
    }

    public function test_completing_twice_stays_one_completion(): void
    {
        $task = $this->task(['due_on' => '2026-09-16']);

        // Nobody waits for the response at the tablet; the second tap
        // happens regularly.
        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-16'])
            ->assertCreated();

        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-16'])
            ->assertCreated();

        $this->assertSame(1, TaskCompletion::query()->count());
    }

    public function test_completing_can_be_undone(): void
    {
        $task = $this->task(['due_on' => '2026-09-16']);

        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-16'])
            ->assertCreated();

        $this->withToken($this->token)
            ->deleteJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-16'])
            ->assertOk();

        $this->assertSame(0, TaskCompletion::query()->count());
    }

    public function test_completed_by_is_recorded(): void
    {
        $child = Person::factory()->create([
            'household_id' => $this->household->id,
            'name' => 'Wilhelmine',
        ]);

        $task = $this->task(['due_on' => '2026-09-16']);

        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", [
                'date' => '2026-09-16',
                'person_id' => $child->id,
            ])
            ->assertCreated();

        $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from=2026-09-16&to=2026-09-16')
            ->assertJsonPath('occurrences.0.done_by', 'Wilhelmine');
    }

    public function test_foreign_task_cannot_be_completed(): void
    {
        $foreign = Task::factory()->create([
            'household_id' => Household::factory()->create()->id,
            'due_on' => '2026-09-16',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$foreign->id}/completion", ['date' => '2026-09-16'])
            ->assertNotFound();

        $this->assertSame(0, TaskCompletion::withoutHouseholdScope()->count());
    }
}
