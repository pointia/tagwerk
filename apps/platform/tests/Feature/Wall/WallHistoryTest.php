<?php

declare(strict_types=1);

namespace Tests\Feature\Wall;

use App\Models\Household;
use App\Models\Person;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WallHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create(['timezone' => 'Europe/Vienna']);

        app(HouseholdContext::class)->setId((int) $this->household->id);

        $device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        $this->token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];
    }

    private function completion(string $title, string $person, string $day): TaskCompletion
    {
        $task = Task::factory()->create([
            'household_id' => $this->household->id,
            'title' => $title,
            'points' => 3,
        ]);

        return TaskCompletion::create([
            'household_id' => $this->household->id,
            'task_id' => $task->id,
            'person_id' => Person::factory()->child()->create([
                'household_id' => $this->household->id,
                'name' => $person,
            ])->id,
            'occurred_on' => $day,
            'completed_at' => $day.' 17:00:00',
        ]);
    }

    public function test_no_access_without_token(): void
    {
        $this->getJson('/api/wall/history')->assertUnauthorized();
    }

    public function test_shows_who_completed_what_when(): void
    {
        $this->completion('Müll rausbringen', 'Lena', now()->toDateString());

        $this->withToken($this->token)
            ->getJson('/api/wall/history')
            ->assertOk()
            ->assertJsonCount(1, 'entries')
            ->assertJsonPath('entries.0.task', 'Müll rausbringen')
            ->assertJsonPath('entries.0.person', 'Lena')
            ->assertJsonPath('entries.0.points', 3);
    }

    public function test_newest_first(): void
    {
        $this->completion('Vorgestern', 'Lena', now()->subDays(2)->toDateString());
        $this->completion('Heute', 'Max', now()->toDateString());

        $this->withToken($this->token)
            ->getJson('/api/wall/history')
            ->assertOk()
            ->assertJsonPath('entries.0.task', 'Heute')
            ->assertJsonPath('entries.1.task', 'Vorgestern');
    }

    public function test_older_entries_fall_out_of_the_range(): void
    {
        $this->completion('Lange her', 'Lena', now()->subDays(20)->toDateString());

        $this->withToken($this->token)
            ->getJson('/api/wall/history?days=7')
            ->assertOk()
            ->assertJsonCount(0, 'entries');
    }

    public function test_foreign_household_stays_out(): void
    {
        $foreign = Household::factory()->create();
        $task = Task::factory()->create(['household_id' => $foreign->id, 'title' => 'Nicht meins']);

        TaskCompletion::create([
            'household_id' => $foreign->id,
            'task_id' => $task->id,
            'person_id' => Person::factory()->create(['household_id' => $foreign->id])->id,
            'occurred_on' => now()->toDateString(),
            'completed_at' => now(),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/wall/history')
            ->assertOk()
            ->assertJsonCount(0, 'entries');
    }
}
