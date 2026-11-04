<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\Event;
use App\Models\Household;
use App\Models\Person;
use App\Models\ProviderAccount;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three-step assignment rule from section 5.2 of the spec.
 */
final class EventAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();
        app(HouseholdContext::class)->setId((int) $this->household->id);
    }

    private function calendar(Person ...$owners): Calendar
    {
        $calendar = Calendar::factory()->create([
            'household_id' => $this->household->id,
            'provider_account_id' => ProviderAccount::factory()->create([
                'household_id' => $this->household->id,
            ])->id,
        ]);
        $calendar->people()->attach(array_map(fn (Person $owner): int => $owner->id, $owners));

        return $calendar;
    }

    private function event(Calendar $calendar): Event
    {
        return Event::factory()->create([
            'household_id' => $this->household->id,
            'calendar_id' => $calendar->id,
        ]);
    }

    public function test_explicit_assignment_wins(): void
    {
        $owner = Person::factory()->create(['household_id' => $this->household->id]);
        $child = Person::factory()->child()->create(['household_id' => $this->household->id]);

        $event = $this->event($this->calendar($owner));
        $event->people()->attach($child);

        $assigned = $event->fresh()->resolvedPeople();

        $this->assertCount(1, $assigned);
        $this->assertSame($child->id, $assigned->first()->id);
    }

    public function test_otherwise_the_calendar_owner_applies(): void
    {
        $owner = Person::factory()->create(['household_id' => $this->household->id]);

        $assigned = $this->event($this->calendar($owner))->resolvedPeople();

        $this->assertCount(1, $assigned);
        $this->assertSame($owner->id, $assigned->first()->id);
    }

    public function test_a_shared_calendar_applies_to_all_its_people(): void
    {
        $lena = Person::factory()->child()->create(['household_id' => $this->household->id]);
        $max = Person::factory()->child()->create(['household_id' => $this->household->id]);

        $assigned = $this->event($this->calendar($lena, $max))->resolvedPeople();

        $this->assertEqualsCanonicalizing([$lena->id, $max->id], $assigned->pluck('id')->all());
    }

    public function test_family_calendar_stays_neutral(): void
    {
        // No owner, no assignment: applies to everyone.
        $this->assertTrue($this->event($this->calendar())->resolvedPeople()->isEmpty());
    }

    public function test_several_people_can_be_assigned(): void
    {
        $calendar = $this->calendar();
        $event = $this->event($calendar);

        $people = Person::factory()->count(2)->create(['household_id' => $this->household->id]);
        $event->people()->attach($people->pluck('id'));

        $this->assertCount(2, $event->fresh()->resolvedPeople());
    }
}
