<?php

declare(strict_types=1);

namespace Tests\Feature\Wall;

use App\Models\Household;
use App\Models\Person;
use App\Models\Task;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WallTaskProgressTest extends TestCase
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

    private function person(string $name): Person
    {
        return Person::factory()->create([
            'household_id' => $this->household->id,
            'name' => $name,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function task(array $attributes): Task
    {
        return Task::factory()->create([
            'household_id' => $this->household->id,
            ...$attributes,
        ]);
    }

    public function test_no_access_without_token(): void
    {
        $this->getJson('/api/wall/tasks/progress?from=2026-09-14&to=2026-09-20')->assertUnauthorized();
    }

    public function test_done_and_total_per_person(): void
    {
        $emma = $this->person('Emma');

        $this->task(['due_on' => '2026-09-16', 'person_id' => $emma->id]);
        $second = $this->task(['due_on' => '2026-09-17', 'person_id' => $emma->id]);

        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$second->id}/completion", [
                'date' => '2026-09-17',
                'person_id' => $emma->id,
            ])
            ->assertCreated();

        $this->withToken($this->token)
            ->getJson('/api/wall/tasks/progress?from=2026-09-14&to=2026-09-20')
            ->assertOk()
            ->assertJsonCount(1, 'progress')
            ->assertJsonPath('progress.0.person_id', $emma->id)
            ->assertJsonPath('progress.0.done', 1)
            ->assertJsonPath('progress.0.total', 2);
    }

    public function test_tasks_without_assignee_count_for_nobody(): void
    {
        $this->task(['due_on' => '2026-09-16', 'person_id' => null]);

        $this->withToken($this->token)
            ->getJson('/api/wall/tasks/progress?from=2026-09-14&to=2026-09-20')
            ->assertOk()
            ->assertJsonCount(0, 'progress');
    }

    public function test_nothing_counts_outside_the_range(): void
    {
        $emma = $this->person('Emma');

        $this->task(['due_on' => '2026-09-30', 'person_id' => $emma->id]);

        $this->withToken($this->token)
            ->getJson('/api/wall/tasks/progress?from=2026-09-14&to=2026-09-20')
            ->assertOk()
            ->assertJsonCount(0, 'progress');
    }

    public function test_a_range_is_required(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/wall/tasks/progress')
            ->assertStatus(422);
    }
}
