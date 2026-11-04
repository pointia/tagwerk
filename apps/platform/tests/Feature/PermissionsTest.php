<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\PersonRole;
use App\Integrations\Calendar\Builtin\BuiltinCalendarProvider;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\Household;
use App\Models\Person;
use App\Models\ProviderAccount;
use App\Models\Task;
use App\Models\User;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Who may change what: a child on the phone only what concerns them, the wall
 * what it could before, the PIN everything.
 */
final class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private Person $lena;

    private Person $max;

    protected function setUp(): void
    {
        parent::setUp();

        // A past day can no longer be ticked: the clock stands on the first day the tests tick.
        $this->travelTo(CarbonImmutable::parse('2026-09-25 08:00', 'Europe/Vienna'));

        Http::preventStrayRequests();

        $this->household = Household::factory()->create(['timezone' => 'Europe/Vienna']);
        app(HouseholdContext::class)->setId((int) $this->household->id);

        $this->lena = Person::factory()->child()->create(['household_id' => $this->household->id, 'name' => 'Lena']);
        $this->max = Person::factory()->child()->create(['household_id' => $this->household->id, 'name' => 'Max']);
    }

    private function phoneOf(Person $person): string
    {
        return User::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $person->id,
        ])->createToken('phone')->plainTextToken;
    }

    private function wall(): string
    {
        $pairing = app(DevicePairingService::class);

        return $pairing->redeem($pairing->createCode($this->household, 'Küche')->pairing_code)['token'];
    }

    private function calendar(Person ...$owners): Calendar
    {
        $calendar = Calendar::factory()->create([
            'household_id' => $this->household->id,
            'provider_account_id' => ProviderAccount::factory()->create([
                'household_id' => $this->household->id,
                'provider' => BuiltinCalendarProvider::KEY,
            ])->id,
        ]);
        $calendar->people()->attach(array_map(fn (Person $owner): int => $owner->id, $owners));

        return $calendar;
    }

    private function event(Calendar $calendar, Person ...$people): Event
    {
        $event = Event::factory()->create([
            'household_id' => $this->household->id,
            'calendar_id' => $calendar->id,
        ]);
        $event->people()->attach(array_map(fn (Person $person): int => $person->id, $people));

        return $event;
    }

    public function test_a_child_deletes_own_events_but_not_those_of_a_sibling(): void
    {
        $phone = $this->phoneOf($this->lena);

        $own = $this->event($this->calendar($this->lena));
        $sibling = $this->event($this->calendar($this->max));
        $family = $this->event($this->calendar());

        $this->withToken($phone)->deleteJson("/api/wall/events/{$own->id}")->assertNoContent();
        $this->withToken($phone)->deleteJson("/api/wall/events/{$sibling->id}")->assertForbidden();
        $this->withToken($phone)->deleteJson("/api/wall/events/{$family->id}")->assertForbidden();
    }

    public function test_a_shared_calendar_is_own_for_each_of_its_people(): void
    {
        $shared = $this->calendar($this->lena, $this->max);
        $outsider = Person::factory()->child()->create(['household_id' => $this->household->id]);

        $this->withToken($this->phoneOf($outsider))
            ->deleteJson("/api/wall/events/{$this->event($shared)->id}")
            ->assertForbidden();
        $this->app['auth']->forgetGuards();

        $this->withToken($this->phoneOf($this->lena))
            ->deleteJson("/api/wall/events/{$this->event($shared)->id}")
            ->assertNoContent();
    }

    public function test_a_child_cannot_hand_an_event_over_to_a_sibling(): void
    {
        $event = $this->event($this->calendar(), $this->lena);

        $this->withToken($this->phoneOf($this->lena))
            ->patchJson("/api/wall/events/{$event->id}", [
                'title' => 'Nicht meins',
                'starts_at' => '2026-09-26T10:00:00+02:00',
                'ends_at' => '2026-09-26T11:00:00+02:00',
                'people' => [$this->max->id],
            ])
            ->assertForbidden();

        $this->assertSame([$this->lena->id], $event->people()->pluck('people.id')->all());
    }

    public function test_a_child_creates_events_only_for_itself(): void
    {
        $phone = $this->phoneOf($this->lena);
        $family = $this->calendar();
        $body = [
            'calendar_id' => $family->id,
            'title' => 'Training',
            'starts_at' => '2026-09-26T10:00:00+02:00',
            'ends_at' => '2026-09-26T11:00:00+02:00',
        ];

        $this->withToken($phone)->postJson('/api/wall/events', $body)->assertForbidden();
        $this->withToken($phone)
            ->postJson('/api/wall/events', [...$body, 'people' => [$this->lena->id]])
            ->assertCreated()
            ->assertJsonPath('can.edit', true);
    }

    public function test_the_agenda_tells_which_events_may_be_changed(): void
    {
        $own = $this->event($this->calendar($this->lena));
        $sibling = $this->event($this->calendar($this->max));

        $events = collect($this->withToken($this->phoneOf($this->lena))
            ->getJson('/api/wall/agenda?from='.now()->subWeeks(2)->toDateString().'&to='.now()->addWeeks(4)->toDateString())
            ->assertOk()
            ->json('events'))->keyBy('id');

        $this->assertTrue($events[$own->id]['can']['edit']);
        $this->assertFalse($events[$sibling->id]['can']['edit']);
    }

    public function test_a_child_ticks_own_and_shared_tasks_but_not_those_of_a_sibling(): void
    {
        $phone = $this->phoneOf($this->lena);
        $task = fn (?Person $person): Task => Task::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $person?->id,
            'due_on' => '2026-09-25',
        ]);

        $this->withToken($phone)
            ->postJson("/api/wall/tasks/{$task($this->lena)->id}/completion", ['date' => '2026-09-25'])
            ->assertCreated();
        $this->withToken($phone)
            ->postJson("/api/wall/tasks/{$task($this->max)->id}/completion", ['date' => '2026-09-25'])
            ->assertForbidden();
        $this->withToken($phone)
            ->postJson("/api/wall/tasks/{$task($this->lena)->id}/completion", ['date' => '2026-09-25', 'person_id' => $this->max->id])
            ->assertForbidden();

        // A task for nobody is credited to whoever ticks it on their phone.
        $forEveryone = $task(null);
        $this->withToken($phone)
            ->postJson("/api/wall/tasks/{$forEveryone->id}/completion", ['date' => '2026-09-25'])
            ->assertCreated();
        $this->assertSame($this->lena->id, $forEveryone->completions()->value('person_id'));
    }

    public function test_a_child_neither_awards_stars_nor_plans_meals_by_default(): void
    {
        $phone = $this->phoneOf($this->lena);

        $this->withToken($phone)
            ->postJson('/api/wall/points', ['person_id' => $this->lena->id, 'points' => 100, 'reason' => 'Ich'])
            ->assertForbidden();
        $this->withToken($phone)
            ->postJson('/api/wall/meals', ['date' => '2026-09-25', 'title' => 'Pizza'])
            ->assertForbidden();
    }

    public function test_granted_permissions_replace_the_role_defaults(): void
    {
        $this->lena->update(['permissions' => [Permission::CalendarEditAll->value, 'removed.permission']]);
        $phone = $this->phoneOf($this->lena);

        $this->withToken($phone)
            ->deleteJson("/api/wall/events/{$this->event($this->calendar())->id}")
            ->assertNoContent();

        // The list replaces the defaults: lists are no longer included.
        $this->withToken($phone)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('permissions', [Permission::CalendarEditAll->value]);
    }

    /** Grandparents join in, but neither run the household nor its tasks. */
    public function test_a_guest_awards_stars_but_does_not_manage(): void
    {
        $grandma = Person::factory()->guest()->create(['household_id' => $this->household->id]);
        $phone = $this->phoneOf($grandma);

        $this->withToken($phone)
            ->postJson('/api/wall/points', ['person_id' => $this->lena->id, 'points' => 2, 'reason' => 'Geholfen'])
            ->assertCreated();
        $this->withToken($phone)
            ->postJson('/api/wall/tasks', ['title' => 'Blumen gießen', 'points' => 1, 'date' => '2026-09-25'])
            ->assertForbidden();

        $permissions = $this->withToken($phone)->getJson('/api/auth/me')->json('permissions');
        $this->assertNotContains(Permission::HouseholdManage->value, $permissions);
    }

    public function test_a_guest_is_refined_like_anyone_else(): void
    {
        $grandma = Person::factory()->guest()->create([
            'household_id' => $this->household->id,
            'permissions' => [...array_map(
                fn (Permission $permission): string => $permission->value,
                Permission::defaultsFor(PersonRole::Guest),
            ), Permission::TasksManage->value],
        ]);

        $this->withToken($this->phoneOf($grandma))
            ->postJson('/api/wall/tasks', ['title' => 'Blumen gießen', 'points' => 1, 'date' => '2026-09-25'])
            ->assertCreated();
    }

    public function test_guests_stand_after_the_family(): void
    {
        $grandma = Person::factory()->guest()->create(['household_id' => $this->household->id, 'position' => 0]);
        $parent = Person::factory()->create(['household_id' => $this->household->id, 'position' => 5]);

        $this->assertSame(
            [$this->lena->id, $this->max->id, $parent->id, $grandma->id],
            Person::query()->ordered()->pluck('id')->filter(fn (int $id): bool => in_array($id, [$this->lena->id, $this->max->id, $parent->id, $grandma->id], true))->values()->all(),
        );
    }

    public function test_an_adult_may_do_everything(): void
    {
        $parent = Person::factory()->create(['household_id' => $this->household->id]);

        $this->withToken($this->phoneOf($parent))
            ->deleteJson("/api/wall/events/{$this->event($this->calendar($this->max))->id}")
            ->assertNoContent();
    }

    public function test_the_wall_keeps_what_it_could_and_the_pin_unlocks_the_rest(): void
    {
        $this->parentWithPin($this->household);
        $wall = $this->wall();

        $this->withToken($wall)
            ->deleteJson("/api/wall/events/{$this->event($this->calendar($this->max))->id}")
            ->assertNoContent();
        $this->withToken($wall)
            ->postJson('/api/wall/points', ['person_id' => $this->lena->id, 'points' => 5, 'reason' => 'Toll'])
            ->assertStatus(423)
            ->assertJsonPath('pin_required', true);

        $this->withToken($wall)->postJson('/api/wall/pin', ['pin' => '1234'])->assertOk();

        $this->withToken($wall)
            ->postJson('/api/wall/points', ['person_id' => $this->lena->id, 'points' => 5, 'reason' => 'Toll'])
            ->assertSuccessful();
    }

    public function test_a_restricted_wall_asks_for_the_pin(): void
    {
        $this->parentWithPin($this->household);
        $wall = $this->wall();
        $this->household->devices()->update(['permissions' => json_encode([Permission::ListsEdit->value])]);

        $this->withToken($wall)
            ->deleteJson("/api/wall/events/{$this->event($this->calendar())->id}")
            ->assertStatus(423);
    }
}
