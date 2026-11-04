<?php

declare(strict_types=1);

namespace Tests\Feature\Wall;

use App\Models\Household;
use App\Models\Person;
use App\Models\PointEntry;
use App\Models\ProviderAccount;
use App\Models\Task;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Creating, changing and removing tasks from the view on the device.
 */
final class WallTaskWriteTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private string $token;

    private Person $child;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create(['timezone' => 'Europe/Vienna']);
        app(HouseholdContext::class)->setId((int) $this->household->id);

        $device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        $this->token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];

        $this->child = Person::factory()->child()->create([
            'household_id' => $this->household->id,
            'name' => 'Max',
        ]);
    }

    /** @return array<string, mixed> */
    private function input(array $overrides = []): array
    {
        return [
            'title' => 'Zähne putzen',
            'icon' => '🦷',
            'points' => 2,
            'person_id' => $this->child->id,
            'date' => '2026-09-21',
            'weekdays' => [],
            ...$overrides,
        ];
    }

    private function unlock(): void
    {
        $this->parentWithPin($this->household);

        $this->withToken($this->token)->postJson('/api/wall/pin', ['pin' => '1234'])->assertOk();
    }

    /**
     * Whoever invents a task decides what earns points — that is up to the
     * parents, otherwise a child writes down „Aufstehen, 50 Punkte".
     */
    public function test_creating_requires_the_pin(): void
    {
        $this->parentWithPin($this->household);

        $this->withToken($this->token)
            ->postJson('/api/wall/tasks', $this->input())
            ->assertStatus(423)
            ->assertJsonPath('pin_required', true);

        $this->assertSame(0, Task::query()->count());
    }

    public function test_created_task_appears_on_its_day(): void
    {
        $this->unlock();

        $this->withToken($this->token)
            ->postJson('/api/wall/tasks', $this->input())
            ->assertCreated();

        $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from=2026-09-21&to=2026-09-21')
            ->assertOk()
            ->assertJsonCount(1, 'occurrences')
            ->assertJsonPath('occurrences.0.title', 'Zähne putzen')
            ->assertJsonPath('occurrences.0.icon', '🦷')
            ->assertJsonPath('occurrences.0.points', 2)
            ->assertJsonPath('occurrences.0.person_name', 'Max')
            ->assertJsonPath('occurrences.0.routine', false);
    }

    /** Tapped weekdays become the rule — and the task becomes a routine. */
    public function test_weekdays_become_the_recurrence(): void
    {
        $this->unlock();

        $this->withToken($this->token)
            ->postJson('/api/wall/tasks', $this->input(['weekdays' => ['MO', 'WE']]))
            ->assertCreated();

        $this->assertSame('FREQ=WEEKLY;BYDAY=MO,WE', Task::query()->value('rrule'));

        $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from=2026-09-21&to=2026-09-27')
            ->assertOk()
            ->assertJsonCount(2, 'occurrences')
            ->assertJsonPath('occurrences.0.recurrence', 'Jeden Montag und Mittwoch');
    }

    public function test_without_person_anyone_may_complete(): void
    {
        $this->unlock();

        $this->withToken($this->token)
            ->postJson('/api/wall/tasks', $this->input(['person_id' => null]))
            ->assertCreated();

        $this->assertNull(Task::query()->value('person_id'));
    }

    public function test_foreign_person_is_rejected(): void
    {
        $this->unlock();

        $foreign = Person::factory()->child()->create([
            'household_id' => Household::factory()->create()->id,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/wall/tasks', $this->input(['person_id' => $foreign->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('person_id');
    }

    public function test_update_applies_title_icon_and_points(): void
    {
        $this->unlock();

        $task = Task::factory()->create([
            'household_id' => $this->household->id,
            'title' => 'Zähne putzen',
            'points' => 2,
        ]);

        $this->withToken($this->token)
            ->patchJson("/api/wall/tasks/{$task->id}", $this->input([
                'title' => 'Zimmer aufräumen',
                'icon' => '🧸',
                'points' => 5,
            ]))
            ->assertOk();

        $task->refresh();

        $this->assertSame('Zimmer aufräumen', $task->title);
        $this->assertSame('🧸', $task->icon);
        $this->assertSame(5, $task->points);
    }

    /**
     * The core: the credit hangs on the completion, and the database would
     * clear both away with the task. On the device it is therefore
     * deactivated, not deleted — the coins stay where they belong.
     */
    public function test_removing_keeps_earned_points(): void
    {
        // A past day can no longer be ticked.
        $this->travelTo(CarbonImmutable::parse('2026-09-21 08:00', 'Europe/Vienna'));
        $this->unlock();

        $task = Task::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $this->child->id,
            'points' => 3,
            'due_on' => '2026-09-21',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => '2026-09-21'])
            ->assertCreated();

        $this->assertSame(3, (int) PointEntry::query()->sum('points'));

        $this->withToken($this->token)
            ->deleteJson("/api/wall/tasks/{$task->id}")
            ->assertNoContent();

        $this->assertSame(3, (int) PointEntry::query()->sum('points'));
        $this->assertSoftDeleted($task);

        $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from=2026-09-21&to=2026-09-21')
            ->assertOk()
            ->assertJsonCount(0, 'occurrences');
    }

    public function test_imported_task_cannot_be_changed_on_the_device(): void
    {
        $this->unlock();

        $account = ProviderAccount::factory()->create(['household_id' => $this->household->id]);

        $task = Task::factory()->create([
            'household_id' => $this->household->id,
            'task_account_id' => $account->id,
            'external_id' => 'fremd-1',
        ]);

        $this->withToken($this->token)
            ->patchJson("/api/wall/tasks/{$task->id}", $this->input())
            ->assertStatus(422);

        $this->withToken($this->token)
            ->deleteJson("/api/wall/tasks/{$task->id}")
            ->assertStatus(422);
    }

    /**
     * Weekdays live on the server so they are not maintained twice; icons
     * come from public/emoji/catalog.json instead.
     */
    public function test_the_list_includes_the_form_options(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/wall/tasks')
            ->assertOk()
            ->assertJsonPath('options.weekdays.MO', 'Montag')
            ->assertJsonMissingPath('options.icons');
    }

    /** Homework for two children: one task, each ticks off on their own. */
    public function test_several_people_make_one_task_per_person(): void
    {
        $sister = Person::factory()->child()->create(['household_id' => $this->household->id, 'name' => 'Lena']);

        $this->withToken($this->token)
            ->postJson('/api/wall/tasks', $this->input([
                'title' => 'Hausaufgaben',
                'person_ids' => [$this->child->id, $sister->id],
                'weekdays' => ['MO', 'TU', 'WE', 'TH', 'FR'],
                'skip_holidays' => true,
            ]))
            ->assertCreated();

        $task = Task::query()->sole();

        $this->assertTrue($task->per_person);
        $this->assertNull($task->person_id);
        $this->assertEqualsCanonicalizing([$this->child->id, $sister->id], $task->per_person_ids);
        $this->assertTrue($task->skip_holidays);

        $occurrences = $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from=2026-09-21&to=2026-09-21')
            ->assertOk()
            ->json('occurrences');

        $this->assertCount(2, $occurrences);
        $this->assertEqualsCanonicalizing([$this->child->id, $sister->id], $occurrences[0]['person_ids']);
    }

    public function test_one_person_stays_an_ordinary_task(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/wall/tasks', $this->input(['person_ids' => [$this->child->id]]))
            ->assertCreated();

        $task = Task::query()->sole();

        $this->assertFalse($task->per_person);
        $this->assertSame($this->child->id, $task->person_id);
    }

    public function test_a_foreign_person_is_rejected(): void
    {
        $foreign = Household::factory()->create();
        $stranger = app(HouseholdContext::class)->forId(
            (int) $foreign->id,
            fn (): Person => Person::factory()->child()->create(['household_id' => $foreign->id]),
        );

        $this->withToken($this->token)
            ->postJson('/api/wall/tasks', $this->input(['person_ids' => [$this->child->id, $stranger->id]]))
            ->assertJsonValidationErrors('person_id');

        $this->assertSame(0, Task::query()->count());
    }

    /** "Every child" from the starter content survives a change at the wall. */
    public function test_changing_a_task_for_every_child_keeps_it_for_every_child(): void
    {
        $sister = Person::factory()->child()->create(['household_id' => $this->household->id]);
        $task = Task::factory()->create([
            'household_id' => $this->household->id,
            'per_person' => true,
            'per_person_ids' => [],
            'rrule' => 'FREQ=DAILY',
        ]);

        $this->withToken($this->token)
            ->patchJson("/api/wall/tasks/{$task->id}", $this->input([
                'person_ids' => [$sister->id, $this->child->id],
                'weekdays' => ['MO'],
            ]))
            ->assertOk();

        $this->assertSame([], $task->fresh()->per_person_ids);
    }

    /** Changing and removing sit behind the PIN, like creating. */
    public function test_changing_and_removing_require_the_pin(): void
    {
        $this->parentWithPin($this->household);
        $task = Task::factory()->create(['household_id' => $this->household->id, 'title' => 'Zähne putzen']);

        $this->withToken($this->token)
            ->patchJson("/api/wall/tasks/{$task->id}", $this->input(['title' => 'Anders']))
            ->assertStatus(423);

        $this->withToken($this->token)
            ->deleteJson("/api/wall/tasks/{$task->id}")
            ->assertStatus(423);

        $task->refresh();
        $this->assertSame('Zähne putzen', $task->title);
        $this->assertTrue((bool) $task->active);
    }

    /**
     * The form preselects the rule's weekdays, not only the tapped one —
     * otherwise saving "Monday and Thursday" from a Monday dropped Thursday.
     */
    public function test_the_list_carries_what_the_form_needs(): void
    {
        $sister = Person::factory()->child()->create(['household_id' => $this->household->id]);

        Task::factory()->create([
            'household_id' => $this->household->id,
            'title' => 'Wöchentlich',
            'due_on' => '2026-09-01',
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO,TH',
            'dayparts' => ['evening', 'morning'],
            'rotation' => [$sister->id, $this->child->id],
        ]);
        Task::factory()->create([
            'household_id' => $this->household->id,
            'title' => 'Monatlich',
            'due_on' => '2026-09-01',
            'rrule' => 'FREQ=MONTHLY;BYDAY=3MO',
        ]);
        Task::factory()->create([
            'household_id' => $this->household->id,
            'title' => 'Einmal',
            'due_on' => '2026-09-21',
            'rrule' => null,
        ]);

        $occurrences = collect($this->withToken($this->token)
            ->getJson('/api/wall/tasks?from=2026-09-21&to=2026-09-21')
            ->assertOk()
            ->assertJsonPath('options.dayparts.0.value', 'morning')
            ->json('occurrences'))->keyBy('title');

        $weekly = $occurrences['Wöchentlich'];
        $this->assertSame(['MO', 'TH'], $weekly['weekdays']);
        $this->assertSame('2026-09-01', $weekly['due_on']);
        $this->assertSame(['morning', 'evening'], $weekly['dayparts']);
        $this->assertSame([$sister->id, $this->child->id], $weekly['rotation']);

        // A rule the device form cannot express is not offered for changing.
        $this->assertNull($occurrences['Monatlich']['weekdays']);
        $this->assertSame([], $occurrences['Einmal']['weekdays']);
    }

    /** Without weekdays the stored rule and its start stay untouched. */
    public function test_changing_without_weekdays_keeps_the_rule(): void
    {
        $task = Task::factory()->create([
            'household_id' => $this->household->id,
            'due_on' => '2026-09-01',
            'rrule' => 'FREQ=MONTHLY;BYDAY=3MO',
        ]);

        $input = $this->input(['title' => 'Neu', 'date' => '2026-09-21']);
        unset($input['weekdays']);

        $this->withToken($this->token)->patchJson("/api/wall/tasks/{$task->id}", $input)->assertOk();

        $task->refresh();
        $this->assertSame('Neu', $task->title);
        $this->assertSame('FREQ=MONTHLY;BYDAY=3MO', $task->rrule);
        $this->assertSame('2026-09-01', $task->due_on->toDateString());
    }

    public function test_dayparts_are_stored_and_kept_when_not_sent(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/wall/tasks', $this->input(['dayparts' => ['morning', 'evening'], 'weekdays' => ['MO']]))
            ->assertCreated();

        $task = Task::query()->sole();
        $this->assertSame(['morning', 'evening'], $task->dayparts);

        $this->withToken($this->token)
            ->patchJson("/api/wall/tasks/{$task->id}", $this->input(['weekdays' => ['MO']]))
            ->assertOk();

        $this->assertSame(['morning', 'evening'], $task->fresh()->dayparts);

        $this->withToken($this->token)
            ->patchJson("/api/wall/tasks/{$task->id}", $this->input(['dayparts' => []]))
            ->assertOk();

        $this->assertNull($task->fresh()->dayparts);
    }

    public function test_an_unknown_daypart_is_rejected(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/wall/tasks', $this->input(['dayparts' => ['midnight']]))
            ->assertJsonValidationErrors('dayparts.0');
    }

    /** Taking turns: the tapped order is the order of turns, from the start date. */
    public function test_rotation_takes_turns_in_the_given_order(): void
    {
        $sister = Person::factory()->child()->create(['household_id' => $this->household->id, 'name' => 'Lena']);

        $this->withToken($this->token)
            ->postJson('/api/wall/tasks', $this->input([
                'title' => 'Müll',
                'person_ids' => [$sister->id, $this->child->id],
                'rotation' => [$sister->id, $this->child->id],
                'weekdays' => ['MO'],
            ]))
            ->assertCreated();

        $task = Task::query()->sole();
        $this->assertSame([$sister->id, $this->child->id], $task->rotation);
        $this->assertFalse($task->per_person);
        $this->assertNull($task->person_id);

        $names = collect($this->withToken($this->token)
            ->getJson('/api/wall/tasks?from=2026-09-21&to=2026-10-04')
            ->assertOk()
            ->json('occurrences'))->pluck('person_name')->all();

        $this->assertSame(['Lena', 'Max'], $names);
    }

    public function test_an_empty_rotation_clears_it(): void
    {
        $sister = Person::factory()->child()->create(['household_id' => $this->household->id]);
        $task = Task::factory()->create([
            'household_id' => $this->household->id,
            'rotation' => [$sister->id, $this->child->id],
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO',
        ]);

        $this->withToken($this->token)
            ->patchJson("/api/wall/tasks/{$task->id}", $this->input(['rotation' => [], 'weekdays' => ['MO']]))
            ->assertOk();

        $task->refresh();
        $this->assertNull($task->rotation);
        $this->assertSame($this->child->id, $task->person_id);
    }

    public function test_a_foreign_person_in_the_rotation_is_rejected(): void
    {
        $foreign = Household::factory()->create();
        $stranger = app(HouseholdContext::class)->forId(
            (int) $foreign->id,
            fn (): Person => Person::factory()->child()->create(['household_id' => $foreign->id]),
        );

        $this->withToken($this->token)
            ->postJson('/api/wall/tasks', $this->input(['rotation' => [$this->child->id, $stranger->id], 'weekdays' => ['MO']]))
            ->assertJsonValidationErrors('person_id');

        $this->assertSame(0, Task::query()->count());
    }
}
