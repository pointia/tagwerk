<?php

declare(strict_types=1);

namespace Tests\Feature\Wall;

use App\Models\Household;
use App\Models\Person;
use App\Models\Reward;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Services\DevicePairingService;
use App\Services\PointLedger;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The points view carries more than the balance: periods for the
 * leaderboard, streaks, weekly goal, badges and the savings goal.
 */
final class WallPointsGamificationTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private Person $lena;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // A Thursday: that way the start of the week lies visibly in the past.
        $this->travelTo(CarbonImmutable::parse('2026-09-17 10:00', 'Europe/Vienna'));

        $this->household = Household::factory()->create(['timezone' => 'Europe/Vienna']);
        app(HouseholdContext::class)->setId((int) $this->household->id);

        $this->lena = Person::factory()->child()->create([
            'household_id' => $this->household->id,
            'name' => 'Lena',
        ]);

        $device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        $this->token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];
    }

    private function completedOn(string $day): void
    {
        $task = Task::factory()->create(['household_id' => $this->household->id]);

        TaskCompletion::create([
            'household_id' => $this->household->id,
            'task_id' => $task->id,
            'person_id' => $this->lena->id,
            'occurred_on' => $day,
            'completed_at' => $day.' 17:00:00',
        ]);
    }

    public function test_week_points_only_count_the_current_week(): void
    {
        $ledger = app(PointLedger::class);

        // The Sunday before still belongs to the previous week.
        $this->travelTo(CarbonImmutable::parse('2026-09-13 12:00', 'Europe/Vienna'));
        $ledger->book((int) $this->lena->id, 9, 'Vorwoche');

        $this->travelTo(CarbonImmutable::parse('2026-09-17 12:00', 'Europe/Vienna'));
        $ledger->book((int) $this->lena->id, 4, 'Diese Woche');

        $this->withToken($this->token)
            ->getJson('/api/wall/points')
            ->assertOk()
            ->assertJsonPath('people.0.balance', 13)
            ->assertJsonPath('people.0.week_points', 4)
            ->assertJsonPath('people.0.month_points', 13);
    }

    public function test_streak_and_badges_are_listed_with_the_person(): void
    {
        foreach (['2026-09-11', '2026-09-12', '2026-09-13', '2026-09-14', '2026-09-15', '2026-09-16', '2026-09-17'] as $day) {
            $this->completedOn($day);
        }

        app(PointLedger::class)->book((int) $this->lena->id, 100, 'Fleiß');

        $response = $this->withToken($this->token)->getJson('/api/wall/points')->assertOk();

        $response->assertJsonPath('people.0.streak', 7);

        $badges = array_column($response->json('people.0.badges'), 'key');

        $this->assertContains('streak-7', $badges);
        $this->assertContains('points-100', $badges);
    }

    public function test_the_weekly_goal_reports_when_it_is_reached(): void
    {
        $this->lena->update(['weekly_goal' => 10]);
        app(PointLedger::class)->book((int) $this->lena->id, 12, 'Woche');

        $this->withToken($this->token)
            ->getJson('/api/wall/points')
            ->assertOk()
            ->assertJsonPath('people.0.weekly_goal', 10)
            ->assertJsonPath('people.0.goal_reached', true);
    }

    public function test_without_a_weekly_goal_it_stays_at_zero(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/wall/points')
            ->assertOk()
            ->assertJsonPath('people.0.weekly_goal', 0)
            ->assertJsonPath('people.0.goal_reached', false);
    }

    public function test_the_savings_goal_shows_what_is_missing(): void
    {
        $reward = Reward::factory()->create([
            'household_id' => $this->household->id,
            'name' => 'Kinobesuch',
            'cost' => 50,
        ]);

        $this->lena->update(['savings_reward_id' => $reward->id]);
        app(PointLedger::class)->book((int) $this->lena->id, 20, 'Gespart');

        $this->withToken($this->token)
            ->getJson('/api/wall/points')
            ->assertOk()
            ->assertJsonPath('people.0.saving.reward', 'Kinobesuch')
            ->assertJsonPath('people.0.saving.missing', 30);
    }

    public function test_the_history_can_be_paged(): void
    {
        $ledger = app(PointLedger::class);

        for ($i = 1; $i <= 25; $i++) {
            $ledger->book((int) $this->lena->id, 1, "Buchung {$i}");
        }

        $first = $this->withToken($this->token)->getJson('/api/wall/points')->assertOk();

        $first->assertJsonCount(20, 'entries');
        $first->assertJsonPath('has_more', true);
        $this->assertSame('Buchung 25', $first->json('entries.0.reason'));

        $second = $this->withToken($this->token)->getJson('/api/wall/points?page=2')->assertOk();

        $second->assertJsonCount(5, 'entries');
        $second->assertJsonPath('has_more', false);
        $this->assertSame('Buchung 5', $second->json('entries.0.reason'));
    }
}
