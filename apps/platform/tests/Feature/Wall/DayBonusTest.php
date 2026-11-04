<?php

declare(strict_types=1);

namespace Tests\Feature\Wall;

use App\Models\Household;
use App\Models\Person;
use App\Models\PointEntry;
use App\Models\Task;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DayBonusTest extends TestCase
{
    use RefreshDatabase;

    private const string DAY = '2026-09-16';

    private Household $household;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // A past day can no longer be ticked: the clock stands on the first day the tests tick.
        $this->travelTo(CarbonImmutable::parse('2026-09-16 08:00', 'Europe/Vienna'));

        $this->household = Household::factory()->create(['timezone' => 'Europe/Vienna', 'day_bonus' => 3]);
        app(HouseholdContext::class)->setId((int) $this->household->id);

        $device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        $this->token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];
    }

    private function child(string $name = 'Max'): Person
    {
        return Person::factory()->child()->create(['household_id' => $this->household->id, 'name' => $name]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function task(?Person $person, array $attributes = []): Task
    {
        return Task::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $person?->id,
            'points' => 2,
            'due_on' => self::DAY,
            ...$attributes,
        ]);
    }

    private function tick(Task $task, array $extra = []): void
    {
        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => self::DAY, ...$extra])
            ->assertCreated();
    }

    private function untick(Task $task): void
    {
        $this->withToken($this->token)
            ->deleteJson("/api/wall/tasks/{$task->id}/completion", ['date' => self::DAY])
            ->assertOk();
    }

    private function bonus(Person $person): ?PointEntry
    {
        return PointEntry::query()->where('person_id', $person->id)->whereNotNull('bonus_on')->first();
    }

    public function test_the_last_task_of_the_day_books_the_bonus(): void
    {
        $max = $this->child();
        $first = $this->task($max);
        $second = $this->task($max);

        $this->tick($first);
        $this->assertNull($this->bonus($max));

        $this->tick($second);

        $bonus = $this->bonus($max);
        $this->assertNotNull($bonus);
        $this->assertSame(3, $bonus->points);
        $this->assertSame(self::DAY, $bonus->bonus_on->toDateString());
        $this->assertSame(7, (int) PointEntry::query()->where('person_id', $max->id)->sum('points'));
    }

    /** Two taps on the wall are one fact — and one bonus. */
    public function test_the_bonus_is_booked_once(): void
    {
        $max = $this->child();
        $task = $this->task($max);

        $this->tick($task);
        $this->tick($task);

        $this->assertSame(1, PointEntry::query()->where('person_id', $max->id)->whereNotNull('bonus_on')->count());
    }

    public function test_undoing_a_task_takes_the_bonus_back(): void
    {
        $max = $this->child();
        $task = $this->task($max);

        $this->tick($task);
        $this->untick($task);

        $this->assertNull($this->bonus($max));
        $this->assertSame(0, (int) PointEntry::query()->where('person_id', $max->id)->sum('points'));

        $this->tick($task);
        $this->assertNotNull($this->bonus($max));
    }

    public function test_no_bonus_when_switched_off(): void
    {
        $this->household->update(['day_bonus' => 0]);
        $max = $this->child();

        $this->tick($this->task($max));

        $this->assertNull($this->bonus($max));
    }

    public function test_no_bonus_without_the_points_area(): void
    {
        $this->household->update(['disabled_features' => ['points']]);
        $max = $this->child();

        $this->tick($this->task($max));

        $this->assertNull($this->bonus($max));
    }

    /** A sibling's open task does not hold Max back, and Max's done day is not Lena's. */
    public function test_only_the_own_tasks_count(): void
    {
        $max = $this->child();
        $lena = $this->child('Lena');
        $this->task($lena);
        $this->task(null);

        $this->tick($this->task($max));

        $this->assertNotNull($this->bonus($max));
        $this->assertNull($this->bonus($lena));
    }

    public function test_each_child_of_a_task_per_person_settles_its_own_day(): void
    {
        $max = $this->child();
        $lena = $this->child('Lena');
        $task = $this->task(null, ['per_person' => true]);

        $this->tick($task, ['for_person_id' => $max->id]);

        $this->assertNotNull($this->bonus($max));
        $this->assertNull($this->bonus($lena));
    }

    /** Every time of day is an occurrence: the morning round alone is not the day. */
    public function test_all_times_of_day_must_be_done(): void
    {
        $max = $this->child();
        $task = $this->task($max, ['dayparts' => ['morning', 'evening']]);

        $this->tick($task, ['daypart' => 'morning']);
        $this->assertNull($this->bonus($max));

        $this->tick($task, ['daypart' => 'evening']);
        $this->assertNotNull($this->bonus($max));
    }

    /** The column shows what a complete day is still worth — nothing without the stars area. */
    public function test_the_task_list_says_what_a_complete_day_is_worth(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from='.self::DAY.'&to='.self::DAY)
            ->assertOk()
            ->assertJsonPath('day_bonus', 3);

        $this->household->update(['disabled_features' => ['points']]);

        $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from='.self::DAY.'&to='.self::DAY)
            ->assertJsonPath('day_bonus', 0);
    }

    /** The column's "+n" shows the bonus on the day it was earned for, not the day it was booked. */
    public function test_the_column_shows_the_bonus_on_its_day(): void
    {
        $max = $this->child();
        $this->tick($this->task($max));

        $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from='.self::DAY.'&to='.self::DAY)
            ->assertOk()
            ->assertJsonFragment(['person_id' => $max->id, 'date' => self::DAY, 'points' => 3, 'day_bonus' => true]);
    }

    /** Extra stars of the same day stay a row of their own: a device replaces the bonus, never the sum. */
    public function test_the_bonus_is_a_row_apart_from_extra_stars(): void
    {
        $max = $this->child();
        $this->tick($this->task($max));

        PointEntry::query()->create(['person_id' => $max->id, 'points' => 2, 'reason' => 'Extra'])
            ->forceFill(['created_at' => CarbonImmutable::parse(self::DAY.' 10:00', 'Europe/Vienna')->utc()])
            ->save();

        $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from='.self::DAY.'&to='.self::DAY)
            ->assertOk()
            ->assertJsonCount(2, 'bonus')
            ->assertJsonFragment(['person_id' => $max->id, 'date' => self::DAY, 'points' => 3, 'day_bonus' => true])
            ->assertJsonFragment(['person_id' => $max->id, 'date' => self::DAY, 'points' => 2, 'day_bonus' => false]);
    }

    /** The tick that completes the day says so — the column shows it without waiting for a load. */
    public function test_the_completing_tick_reports_the_bonus(): void
    {
        $max = $this->child();
        $first = $this->task($max);
        $last = $this->task($max);

        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$first->id}/completion", ['date' => self::DAY])
            ->assertJsonPath('day_bonus', null);

        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$last->id}/completion", ['date' => self::DAY])
            ->assertJsonPath('day_bonus', ['person_id' => $max->id, 'points' => 3]);

        $this->withToken($this->token)
            ->deleteJson("/api/wall/tasks/{$last->id}/completion", ['date' => self::DAY])
            ->assertOk()
            ->assertJsonPath('day_bonus', ['person_id' => $max->id, 'points' => -3]);
    }

    /** A holiday: the task cannot be done, the rest of the day still earns the bonus. */
    public function test_a_skipped_task_earns_no_stars_but_completes_the_day(): void
    {
        $max = $this->child();
        $done = $this->task($max);
        $skipped = $this->task($max);

        $this->tick($done);

        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$skipped->id}/completion", ['date' => self::DAY, 'person_id' => $max->id, 'skipped' => true])
            ->assertCreated()
            ->assertJsonPath('points', 0)
            ->assertJsonPath('day_bonus', ['person_id' => $max->id, 'points' => 3]);

        $completion = $skipped->completions()->sole();
        $this->assertTrue($completion->skipped);
        $this->assertNull($completion->person_id);
        $this->assertNull($completion->pointEntry);
        // Two for the task done, three for the day — nothing for the skipped one.
        $this->assertSame(5, (int) PointEntry::query()->where('person_id', $max->id)->sum('points'));

        $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from='.self::DAY.'&to='.self::DAY)
            ->assertOk()
            ->assertJsonFragment(['task_id' => $skipped->id, 'skipped' => true, 'earned' => 0]);
    }

    /** Skipping is a parent's decision: a child at the wall would skip the hard task. */
    public function test_skipping_needs_the_parent_pin(): void
    {
        $this->parentWithPin($this->household);
        $task = $this->task($this->child());

        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => self::DAY, 'skipped' => true])
            ->assertStatus(423);

        $this->assertSame(0, $task->completions()->count());
    }
}
