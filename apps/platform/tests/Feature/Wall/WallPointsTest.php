<?php

declare(strict_types=1);

namespace Tests\Feature\Wall;

use App\Models\Household;
use App\Models\Person;
use App\Models\PointEntry;
use App\Models\Task;
use App\Models\User;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class WallPointsTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // A past day can no longer be ticked: the clock stands on the first day the tests tick.
        $this->travelTo(CarbonImmutable::parse('2026-09-16 08:00', 'Europe/Vienna'));

        $this->household = Household::factory()->create(['timezone' => 'Europe/Vienna']);
        app(HouseholdContext::class)->setId((int) $this->household->id);

        $device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        $this->token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];
    }

    private function book(Person $person, int $points): void
    {
        PointEntry::query()->create(['household_id' => $this->household->id, 'person_id' => $person->id, 'points' => $points, 'reason' => 'Start']);
    }

    private function child(string $name = 'Max'): Person
    {
        return Person::factory()->child()->create([
            'household_id' => $this->household->id,
            'name' => $name,
        ]);
    }

    public function test_no_access_without_token(): void
    {
        $this->getJson('/api/wall/points')->assertUnauthorized();
    }

    public function test_balance_is_the_sum_of_the_entries(): void
    {
        $child = $this->child();

        foreach ([10, 5, -3] as $points) {
            PointEntry::factory()->create([
                'household_id' => $this->household->id,
                'person_id' => $child->id,
                'points' => $points,
            ]);
        }

        $this->withToken($this->token)
            ->getJson('/api/wall/points')
            ->assertOk()
            ->assertJsonPath('people.0.name', 'Max')
            ->assertJsonPath('people.0.balance', 12);
    }

    /** Filtered on the server, so paging on stays filtered too. */
    public function test_the_bookings_filter_by_child_and_sign(): void
    {
        $max = $this->child();
        $lena = $this->child('Lena');
        $this->book($max, 3);
        $this->book($max, -1);
        $this->book($lena, 2);

        $this->withToken($this->token)
            ->getJson("/api/wall/points?person={$max->id}")
            ->assertOk()
            ->assertJsonCount(2, 'entries')
            ->assertJsonPath('entries.0.person_id', $max->id)
            ->assertJsonPath('entries.1.person_id', $max->id);

        $this->withToken($this->token)
            ->getJson('/api/wall/points?sign=minus')
            ->assertJsonCount(1, 'entries')
            ->assertJsonPath('entries.0.points', -1);

        $this->withToken($this->token)
            ->getJson("/api/wall/points?person={$lena->id}&sign=plus")
            ->assertJsonCount(1, 'entries')
            ->assertJsonPath('entries.0.points', 2);

        $this->withToken($this->token)
            ->getJson('/api/wall/points?sign=sideways')
            ->assertJsonValidationErrors('sign');
    }

    public function test_points_are_readable_without_pin(): void
    {
        $this->parentWithPin($this->household);
        $this->child();

        $this->withToken($this->token)
            ->getJson('/api/wall/points')
            ->assertOk();
    }

    public function test_completing_credits_the_task_points(): void
    {
        $child = $this->child();

        $task = Task::factory()->create([
            'household_id' => $this->household->id,
            'title' => 'Müll rausbringen',
            'points' => 5,
            'person_id' => $child->id,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-16'])
            ->assertCreated()
            ->assertJsonPath('points', 5);

        $this->assertSame(5, (int) PointEntry::query()->where('person_id', $child->id)->sum('points'));
    }

    public function test_completing_twice_books_only_once(): void
    {
        $child = $this->child();

        $task = Task::factory()->create([
            'household_id' => $this->household->id,
            'points' => 5,
            'person_id' => $child->id,
        ]);

        foreach ([1, 2] as $ignored) {
            $this->withToken($this->token)
                ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-16'])
                ->assertCreated();
        }

        $this->assertSame(1, PointEntry::query()->where('person_id', $child->id)->count());
    }

    /**
     * Taking a tick back takes its stars back too, and ticking again books
     * them anew — the entry hangs on the completion, not on the task.
     */
    public function test_undoing_takes_the_stars_back(): void
    {
        $child = $this->child();
        $task = Task::factory()->create([
            'household_id' => $this->household->id,
            'points' => 5,
            'person_id' => $child->id,
        ]);
        $url = "/api/wall/tasks/{$task->id}/completion";

        $this->withToken($this->token)->postJson($url, ['date' => '2026-09-16'])->assertCreated();
        $this->withToken($this->token)->deleteJson($url, ['date' => '2026-09-16'])->assertOk();

        $this->assertSame(0, PointEntry::query()->where('person_id', $child->id)->count());
        $this->withToken($this->token)->getJson('/api/wall/points')->assertJsonPath('people.0.balance', 0);

        $this->withToken($this->token)->postJson($url, ['date' => '2026-09-16'])->assertJsonPath('points', 5);
        $this->assertSame(5, (int) PointEntry::query()->where('person_id', $child->id)->sum('points'));
    }

    public function test_without_person_no_entry_is_created(): void
    {
        $task = Task::factory()->create([
            'household_id' => $this->household->id,
            'points' => 5,
            'person_id' => null,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-16'])
            ->assertCreated()
            ->assertJsonPath('points', 0);

        $this->assertSame(0, PointEntry::query()->count());
    }

    public function test_correction_without_pin_is_locked(): void
    {
        $this->parentWithPin($this->household);
        $child = $this->child();

        $this->withToken($this->token)
            ->postJson('/api/wall/points', [
                'person_id' => $child->id,
                'points' => 10,
                'reason' => 'Hat geholfen',
            ])
            ->assertStatus(423)
            ->assertJsonPath('pin_required', true);

        $this->assertSame(0, PointEntry::query()->count());
    }

    /** A child reading "+3" later should know what for. */
    public function test_extra_stars_need_a_reason(): void
    {
        $child = $this->child();

        $this->withToken($this->token)
            ->postJson('/api/wall/points', ['person_id' => $child->id, 'points' => 3, 'reason' => '  '])
            ->assertJsonValidationErrors('reason');

        $this->assertSame(0, PointEntry::query()->count());
    }

    public function test_correction_after_unlocking(): void
    {
        $this->parentWithPin($this->household);
        $child = $this->child();

        $this->withToken($this->token)
            ->postJson('/api/wall/pin', ['pin' => '1234'])
            ->assertOk()
            ->assertJsonPath('unlocked', true);

        $this->book($child, 3);

        $this->withToken($this->token)
            ->postJson('/api/wall/points', [
                'person_id' => $child->id,
                'points' => -1,
                'reason' => 'Abgezogen',
            ])
            ->assertCreated();

        $this->assertSame(2, PointEntry::balanceFor($child->id));
    }

    /*
     * One star at a time: a bigger deduction reads like a price list ("hitting
     * costs three"), and the history shows each one with its own reason.
     */
    public function test_a_deduction_is_one_star_at_most(): void
    {
        $child = $this->child();
        $this->book($child, 10);

        $this->withToken($this->token)
            ->postJson('/api/wall/points', ['person_id' => $child->id, 'points' => -2, 'reason' => 'Gehauen'])
            ->assertJsonValidationErrors('points');

        $this->assertSame(10, PointEntry::balanceFor($child->id));
    }

    /** No debts: below zero no reward is in reach any more, and nothing pays off. */
    public function test_a_deduction_never_goes_below_zero(): void
    {
        $child = $this->child();

        $this->withToken($this->token)
            ->postJson('/api/wall/points', ['person_id' => $child->id, 'points' => -1, 'reason' => 'Gehauen'])
            ->assertJsonValidationErrors('points');

        $this->assertSame(0, PointEntry::balanceFor($child->id));
    }

    public function test_wrong_pin_does_not_unlock(): void
    {
        $this->parentWithPin($this->household);

        $this->withToken($this->token)
            ->postJson('/api/wall/pin', ['pin' => '9999'])
            ->assertStatus(422);

        $this->withToken($this->token)
            ->getJson('/api/wall/pin')
            ->assertJsonPath('unlocked', false);
    }

    /** Without a PIN set everything is open on the tablet — that is the out-of-the-box state. */
    public function test_without_pin_set_the_correction_is_open(): void
    {
        $child = $this->child();

        $this->withToken($this->token)
            ->postJson('/api/wall/points', [
                'person_id' => $child->id,
                'points' => 3,
                'reason' => 'Hat geholfen',
            ])
            ->assertCreated();
    }

    public function test_locking_ends_the_unlock(): void
    {
        $this->parentWithPin($this->household);

        $this->withToken($this->token)->postJson('/api/wall/pin', ['pin' => '1234'])->assertOk();
        $this->withToken($this->token)->deleteJson('/api/wall/pin')->assertNoContent();

        $this->withToken($this->token)
            ->getJson('/api/wall/pin')
            ->assertJsonPath('unlocked', false);
    }

    public function test_foreign_person_is_rejected(): void
    {
        $foreign = Person::factory()->child()->create([
            'household_id' => Household::factory()->create()->id,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/wall/points', [
                'person_id' => $foreign->id,
                'points' => 10,
                'reason' => 'Fremd',
            ])
            ->assertStatus(422);
    }

    /** A past day is history: neither a late tick nor a take-back changes it. */
    public function test_a_past_day_can_no_longer_be_ticked(): void
    {
        $child = $this->child();
        $task = Task::factory()->create([
            'household_id' => $this->household->id,
            'points' => 5,
            'person_id' => $child->id,
        ]);
        $url = "/api/wall/tasks/{$task->id}/completion";

        $this->withToken($this->token)->postJson($url, ['date' => '2026-09-16'])->assertCreated();

        // 00:30 in Vienna is already the next day there, though not yet in UTC.
        $this->travelTo(CarbonImmutable::parse('2026-09-17 00:30', 'Europe/Vienna'));

        $this->withToken($this->token)->deleteJson($url, ['date' => '2026-09-16'])->assertJsonValidationErrors('date');
        $this->withToken($this->token)->postJson($url, ['date' => '2026-09-15'])->assertJsonValidationErrors('date');
        $this->withToken($this->token)->postJson($url, ['date' => '2026-09-17'])->assertCreated();

        $this->assertSame(10, (int) PointEntry::query()->where('person_id', $child->id)->sum('points'));
    }

    private function parentPhone(): string
    {
        $parent = Person::factory()->create(['household_id' => $this->household->id, 'name' => 'Mama']);

        return User::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $parent->id,
        ])->createToken('phone')->plainTextToken;
    }

    /**
     * A tick forgotten on the wall is caught up on the phone: someone is
     * signed in there, so the late stars have a name behind them. They go to
     * the child, and so does the day bonus of that day.
     */
    public function test_an_account_catches_up_on_a_past_day(): void
    {
        $this->household->update(['day_bonus' => 3]);
        $child = $this->child();
        $task = Task::factory()->create([
            'household_id' => $this->household->id,
            'points' => 5,
            'person_id' => $child->id,
        ]);
        $url = "/api/wall/tasks/{$task->id}/completion";
        $phone = $this->parentPhone();

        $this->travelTo(CarbonImmutable::parse('2026-09-18 08:00', 'Europe/Vienna'));

        $this->withToken($phone)->postJson($url, ['date' => '2026-09-16'])
            ->assertCreated()
            ->assertJsonPath('points', 5)
            ->assertJsonPath('day_bonus', ['person_id' => $child->id, 'points' => 3]);

        $this->assertSame(8, (int) PointEntry::query()->where('person_id', $child->id)->sum('points'));
        $this->assertSame('2026-09-16', PointEntry::query()->whereNotNull('bonus_on')->sole()->bonus_on->toDateString());

        Auth::forgetGuards();

        // A wrong late tick can be taken back the same way.
        $this->withToken($phone)->deleteJson($url, ['date' => '2026-09-16'])->assertOk();
        $this->assertSame(0, (int) PointEntry::query()->where('person_id', $child->id)->sum('points'));
    }

    /** Only as far back as the day view reaches: what happened, not an archive. */
    public function test_an_account_cannot_tick_beyond_the_history(): void
    {
        $task = Task::factory()->create(['household_id' => $this->household->id, 'points' => 5]);

        $this->withToken($this->parentPhone())
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-08-18'])
            ->assertJsonValidationErrors('date');
    }
}
