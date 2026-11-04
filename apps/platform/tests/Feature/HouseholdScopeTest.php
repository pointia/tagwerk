<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Household;
use App\Models\Person;
use App\Models\Task;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HouseholdScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_queries_return_only_the_active_household(): void
    {
        $own = Household::factory()->create();
        $foreign = Household::factory()->create();

        Person::factory()->create(['household_id' => $own->id, 'name' => 'Own']);
        Person::factory()->create(['household_id' => $foreign->id, 'name' => 'Foreign']);

        app(HouseholdContext::class)->setId($own->id);

        $this->assertSame(['Own'], Person::query()->pluck('name')->all());
    }

    public function test_new_records_inherit_the_active_household(): void
    {
        $household = Household::factory()->create();
        app(HouseholdContext::class)->setId($household->id);

        $person = Person::create(['name' => 'Unspecified', 'color' => '#123456']);

        $this->assertSame($household->id, $person->household_id);
    }

    public function test_without_context_nothing_is_visible(): void
    {
        Person::factory()->create();
        app(HouseholdContext::class)->setId(null);

        $this->assertCount(0, Person::query()->get());
    }

    public function test_background_processes_can_bypass_the_scope(): void
    {
        Person::factory()->create();
        Person::factory()->create();
        app(HouseholdContext::class)->setId(null);

        $this->assertCount(2, Person::withoutHouseholdScope()->get());
    }

    public function test_for_id_restores_the_previous_context(): void
    {
        $a = Household::factory()->create();
        $b = Household::factory()->create();
        Person::factory()->create(['household_id' => $b->id, 'name' => 'B-Person']);

        $context = app(HouseholdContext::class);
        $context->setId($a->id);

        $names = $context->forId($b->id, fn (): array => Person::query()->pluck('name')->all());

        $this->assertSame(['B-Person'], $names);
        $this->assertSame($a->id, $context->id());
    }

    /**
     * Route model binding needs the household before it looks things up.
     *
     * Laravel sorts route middleware by a fixed priority list.
     * SubstituteBindings is on it, ResolveHousehold is not — without being
     * placed, the binding looks up with an empty context and the global scope
     * finds nothing. On the wall this shows up as a 404 when completing,
     * although the list just delivered the same task.
     *
     * The context is cleared explicitly here: as a singleton it survives the
     * data setup in the test and would otherwise hide the error.
     */
    public function test_binding_finds_the_task_even_without_previously_set_context(): void
    {
        $household = Household::factory()->create(['timezone' => 'Europe/Vienna']);
        app(HouseholdContext::class)->setId((int) $household->id);

        $task = Task::factory()->create([
            'household_id' => $household->id,
            'due_on' => '2026-09-16',
            'rrule' => null,
        ]);

        $device = app(DevicePairingService::class)->createCode($household, 'Küche');
        $token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];

        app(HouseholdContext::class)->setId(null);

        $this->withToken($token)
            ->postJson("/api/wall/tasks/{$task->id}/completion", ['date' => now('Europe/Vienna')->toDateString()])
            ->assertCreated();
    }
}
