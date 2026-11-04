<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Household;
use App\Models\Person;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Services\Gamification;
use App\Services\PointLedger;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GamificationTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private Person $lena;

    private Gamification $gamification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create(['timezone' => 'Europe/Vienna']);
        app(HouseholdContext::class)->setId((int) $this->household->id);

        $this->lena = Person::factory()->child()->create([
            'household_id' => $this->household->id,
            'name' => 'Lena',
        ]);

        $this->gamification = app(Gamification::class);
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

    public function test_points_in_a_period_only_count_inside_it(): void
    {
        $ledger = app(PointLedger::class);

        $this->travelTo(CarbonImmutable::parse('2026-09-14 10:00', 'Europe/Vienna'));
        $ledger->book((int) $this->lena->id, 5, 'Vorige Woche');

        $this->travelTo(CarbonImmutable::parse('2026-09-17 10:00', 'Europe/Vienna'));
        $ledger->book((int) $this->lena->id, 3, 'Diese Woche');

        $week = $this->gamification->pointsBetween(
            CarbonImmutable::parse('2026-09-16 00:00', 'Europe/Vienna'),
            CarbonImmutable::parse('2026-09-18 00:00', 'Europe/Vienna'),
        );

        $this->assertSame([(int) $this->lena->id => 3], $week);
    }

    /**
     * The week boundary applies in the household's time zone: an entry on
     * Monday at 00:30 Vienna time is still Sunday in UTC.
     */
    public function test_the_period_follows_the_household_time_zone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-14 00:30', 'Europe/Vienna'));
        app(PointLedger::class)->book((int) $this->lena->id, 7, 'Montag früh');

        $week = $this->gamification->pointsBetween(
            CarbonImmutable::parse('2026-09-14 00:00', 'Europe/Vienna'),
            CarbonImmutable::parse('2026-09-21 00:00', 'Europe/Vienna'),
        );

        $this->assertSame([(int) $this->lena->id => 7], $week);
    }

    /**
     * Stars are collected to be spent: redeeming a reward must not take back
     * the week's goal. A parent's deduction still counts — it is meant to.
     */
    public function test_redeeming_does_not_lower_the_period(): void
    {
        $ledger = app(PointLedger::class);
        $ledger->book((int) $this->lena->id, 20, 'Verdient');
        $ledger->book((int) $this->lena->id, -1, 'Abzug');

        $reward = Reward::factory()->create(['household_id' => $this->lena->household_id, 'cost' => 15]);
        $redemption = RewardRedemption::factory()->create([
            'household_id' => $this->lena->household_id,
            'reward_id' => $reward->id,
            'person_id' => $this->lena->id,
            'cost' => 15,
        ]);
        $this->assertSame('approved', $ledger->approveRedemption($redemption));

        $week = $this->gamification->pointsBetween(
            CarbonImmutable::now()->startOfWeek(),
            CarbonImmutable::now()->endOfWeek(),
        );

        $this->assertSame([(int) $this->lena->id => 19], $week);
        $this->assertSame(4, $ledger->balances()[$this->lena->id]);
    }

    public function test_collected_ignores_deductions(): void
    {
        $ledger = app(PointLedger::class);
        $ledger->book((int) $this->lena->id, 20, 'Verdient');
        $ledger->book((int) $this->lena->id, -15, 'Eingelöst');

        $this->assertSame([(int) $this->lena->id => 20], $this->gamification->collected());
    }

    public function test_a_streak_counts_consecutive_days(): void
    {
        $this->completedOn('2026-09-15');
        $this->completedOn('2026-09-16');
        $this->completedOn('2026-09-17');

        $streaks = $this->gamification->streaks(CarbonImmutable::parse('2026-09-17'));

        $this->assertSame(3, $streaks[(int) $this->lena->id]);
    }

    public function test_a_gap_ends_the_streak(): void
    {
        $this->completedOn('2026-09-13');
        $this->completedOn('2026-09-16');
        $this->completedOn('2026-09-17');

        $streaks = $this->gamification->streaks(CarbonImmutable::parse('2026-09-17'));

        $this->assertSame(2, $streaks[(int) $this->lena->id]);
    }

    /** In the morning the streak should hold until the day is over. */
    public function test_the_streak_holds_while_nothing_is_done_today_yet(): void
    {
        $this->completedOn('2026-09-15');
        $this->completedOn('2026-09-16');

        $streaks = $this->gamification->streaks(CarbonImmutable::parse('2026-09-17'));

        $this->assertSame(2, $streaks[(int) $this->lena->id]);
    }

    public function test_the_streak_breaks_after_a_skipped_day(): void
    {
        $this->completedOn('2026-09-14');
        $this->completedOn('2026-09-15');

        $streaks = $this->gamification->streaks(CarbonImmutable::parse('2026-09-17'));

        $this->assertSame(0, $streaks[(int) $this->lena->id]);
    }

    public function test_badges_show_the_highest_step_of_each_ladder(): void
    {
        $keys = fn (int $streak, int $collected): array => array_column(
            $this->gamification->badges($streak, $collected),
            'key',
        );

        $this->assertSame([], $keys(0, 0));
        $this->assertSame(['first'], $keys(2, 40));
        $this->assertSame(['streak-7', 'points-100'], $keys(7, 100));
        $this->assertSame(['streak-30', 'points-100'], $keys(34, 120));
        $this->assertSame(['streak-30', 'points-500'], $keys(30, 500));
    }

    public function test_the_savings_goal_shows_what_is_missing(): void
    {
        $reward = Reward::factory()->create([
            'household_id' => $this->household->id,
            'name' => 'Kinobesuch',
            'cost' => 50,
        ]);

        $this->lena->update(['savings_reward_id' => $reward->id]);

        $goal = $this->gamification->savingsGoal($this->lena->fresh(), balance: 20);

        $this->assertSame('Kinobesuch', $goal['reward']);
        $this->assertSame(30, $goal['missing']);
        $this->assertSame(0.4, $goal['share']);
    }

    public function test_without_a_savings_goal_nothing_comes_back(): void
    {
        $this->assertNull($this->gamification->savingsGoal($this->lena, balance: 20));
    }
}
