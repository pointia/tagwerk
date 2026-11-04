<?php

declare(strict_types=1);

namespace Tests\Feature\Wall;

use App\Jobs\AnnounceHouseholdUpdateJob;
use App\Models\Household;
use App\Models\Person;
use App\Models\PointEntry;
use App\Models\Task;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Assignment rotation for routines — "Max this week, Lena next".
 *
 * The assignee is derived, not stored: it is the running number of the
 * occurrence since the rule began, modulo the length of the list. Only that
 * way can the wall show weeks ahead whose turn it is without anyone
 * precomputing and maintaining the dates.
 *
 * Only the override is stored. If the timetable changes, you swap a single
 * week without touching the rule.
 *
 * All dates here are Mondays: 2026-09-07, -14, -21 and -28.
 */
final class WallTaskRotationTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private string $token;

    private Person $max;

    private Person $lena;

    private Person $papa;

    protected function setUp(): void
    {
        parent::setUp();

        // A past day can no longer be ticked: the clock stands on the first day the tests tick.
        $this->travelTo(CarbonImmutable::parse('2026-09-07 08:00', 'Europe/Vienna'));

        $this->household = Household::factory()->create(['timezone' => 'Europe/Vienna']);
        app(HouseholdContext::class)->setId((int) $this->household->id);

        $device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        $this->token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];

        $this->max = $this->person('Max');
        $this->lena = $this->person('Lena');
        $this->papa = $this->person('Papa');
    }

    private function person(string $name): Person
    {
        return Person::factory()->create([
            'household_id' => $this->household->id,
            'name' => $name,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function routine(array $attributes = []): Task
    {
        return Task::factory()->create([
            'household_id' => $this->household->id,
            'title' => 'Müll rausbringen',
            'due_on' => '2026-09-07',
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO',
            'points' => 5,
            ...$attributes,
        ]);
    }

    /** @return array<int, string|null> */
    private function assignees(string $from, string $to): array
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/wall/tasks?from={$from}&to={$to}")
            ->assertOk();

        return array_column($response->json('occurrences'), 'person_name');
    }

    private function unlock(): void
    {
        $this->parentWithPin($this->household);

        $this->withToken($this->token)
            ->postJson('/api/wall/pin', ['pin' => '1234'])
            ->assertOk();
    }

    public function test_without_rotation_the_fixed_assignee_stays(): void
    {
        $this->routine(['person_id' => $this->max->id]);

        $this->assertSame(
            ['Max', 'Max', 'Max'],
            $this->assignees('2026-09-07', '2026-09-21'),
        );
    }

    public function test_rotation_changes_with_every_occurrence(): void
    {
        $this->routine(['rotation' => [$this->max->id, $this->lena->id]]);

        $this->assertSame(
            ['Max', 'Lena', 'Max', 'Lena'],
            $this->assignees('2026-09-07', '2026-09-28'),
        );
    }

    /**
     * The actual reason for deriving from the running number: when a later
     * window is queried, the same person must come out as when querying from
     * the start. If the service counted within the range, every week would
     * start with Max again.
     */
    public function test_a_later_window_shows_the_same_sequence(): void
    {
        $this->routine(['rotation' => [$this->max->id, $this->lena->id]]);

        $this->assertSame(
            ['Max', 'Lena'],
            $this->assignees('2026-09-21', '2026-09-28'),
        );
    }

    /** Whoever misses their week is still done — otherwise everything piles up. */
    public function test_a_skipped_occurrence_does_not_shift_the_sequence(): void
    {
        $task = $this->routine(['rotation' => [$this->max->id, $this->lena->id]]);

        // Max never ticks off his first occurrence.
        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-14'])
            ->assertCreated();

        $this->assertSame(
            ['Max', 'Lena', 'Max', 'Lena'],
            $this->assignees('2026-09-07', '2026-09-28'),
        );
    }

    public function test_completing_without_person_credits_the_assignee(): void
    {
        $task = $this->routine(['rotation' => [$this->max->id, $this->lena->id]]);

        // 2026-09-14 is the second occurrence — so Lena.
        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-14'])
            ->assertCreated()
            ->assertJsonPath('points', 5);

        $this->assertSame(
            $this->lena->id,
            PointEntry::query()->value('person_id'),
        );
    }

    public function test_override_wins_over_the_rule(): void
    {
        $task = $this->routine(['rotation' => [$this->max->id, $this->lena->id]]);

        $this->unlock();

        $this->withToken($this->token)
            ->putJson("/api/wall/tasks/{$task->id}/assignment", [
                'date' => '2026-09-21',
                'person_id' => $this->papa->id,
            ])
            ->assertOk()
            ->assertJsonPath('person_id', $this->papa->id);

        // Only that one week changes; the rule then continues unchanged
        // instead of shifting by one place.
        $this->assertSame(
            ['Max', 'Lena', 'Papa', 'Lena'],
            $this->assignees('2026-09-07', '2026-09-28'),
        );
    }

    public function test_reset_restores_the_rule(): void
    {
        $task = $this->routine(['rotation' => [$this->max->id, $this->lena->id]]);

        $this->unlock();

        $this->withToken($this->token)
            ->putJson("/api/wall/tasks/{$task->id}/assignment", [
                'date' => '2026-09-21',
                'person_id' => $this->papa->id,
            ])
            ->assertOk();

        $this->withToken($this->token)
            ->deleteJson("/api/wall/tasks/{$task->id}/assignment", ['date' => '2026-09-21'])
            ->assertOk()
            // The response names the assignee that applies again: only the
            // server can derive it.
            ->assertJsonPath('person_name', 'Max');

        $this->assertSame(
            ['Max', 'Lena', 'Max', 'Lena'],
            $this->assignees('2026-09-07', '2026-09-28'),
        );
    }

    /** Otherwise a child would simply push their week onto a sibling. */
    public function test_changing_the_assignment_needs_the_parent_pin(): void
    {
        $task = $this->routine(['rotation' => [$this->max->id, $this->lena->id]]);

        $this->parentWithPin($this->household);

        $this->withToken($this->token)
            ->putJson("/api/wall/tasks/{$task->id}/assignment", [
                'date' => '2026-09-21',
                'person_id' => $this->papa->id,
            ])
            ->assertStatus(423)
            ->assertJsonPath('pin_required', true);

        $this->assertSame(
            ['Max', 'Lena', 'Max', 'Lena'],
            $this->assignees('2026-09-07', '2026-09-28'),
        );
    }

    /**
     * A swap must appear on the other devices immediately — otherwise the
     * phone shows the old person as assignee until the next fetch.
     */
    public function test_swap_is_announced_to_the_devices(): void
    {
        $task = $this->routine(['rotation' => [$this->max->id, $this->lena->id]]);

        $this->unlock();
        Queue::fake();

        $this->withToken($this->token)
            ->putJson("/api/wall/tasks/{$task->id}/assignment", [
                'date' => '2026-09-21',
                'person_id' => $this->papa->id,
            ])
            ->assertOk();

        Queue::assertPushed(
            AnnounceHouseholdUpdateJob::class,
            fn (AnnounceHouseholdUpdateJob $job): bool => $job->area === 'tasks',
        );
    }

    /** The query does not find a foreign person through the household scope. */
    public function test_foreign_person_is_not_assigned(): void
    {
        $task = $this->routine(['rotation' => [$this->max->id, $this->lena->id]]);
        $foreign = Person::factory()->create(['household_id' => Household::factory()]);

        $this->unlock();

        $this->withToken($this->token)
            ->putJson("/api/wall/tasks/{$task->id}/assignment", [
                'date' => '2026-09-21',
                'person_id' => $foreign->id,
            ])
            ->assertStatus(422);
    }
}
