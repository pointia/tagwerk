<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PersonRole;
use App\Models\Household;
use App\Models\Membership;
use App\Models\Person;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PersonTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_belongs_to_household_and_holds_role_as_enum(): void
    {
        $household = Household::factory()->create();
        app(HouseholdContext::class)->setId($household->id);

        $person = Person::create([
            'name' => 'Max',
            'color' => '#2E7D32',
            'role' => PersonRole::Child,
        ]);

        $this->assertSame(PersonRole::Child, $person->fresh()->role);
        $this->assertSame($household->id, $person->fresh()->household_id);
    }

    public function test_person_without_login_is_allowed(): void
    {
        $household = Household::factory()->create();
        app(HouseholdContext::class)->setId($household->id);

        $person = Person::factory()->create(['role' => PersonRole::Child]);

        $this->assertFalse(Membership::query()->where('person_id', $person->id)->exists());
    }

    public function test_role_returns_german_label(): void
    {
        $this->assertSame('Kind', PersonRole::Child->label());
        $this->assertSame('Erwachsene:r', PersonRole::Adult->label());
    }

    /**
     * Children by age, the oldest first; then the adults in the order the
     * household set. Names say nothing about who the family puts first.
     */
    public function test_people_are_ordered_children_by_age_then_adults_by_position(): void
    {
        $household = Household::factory()->create();
        app(HouseholdContext::class)->setId($household->id);

        Person::factory()->for($household)->create(['name' => 'Papa', 'role' => PersonRole::Adult, 'position' => 2]);
        Person::factory()->for($household)->create(['name' => 'Ben', 'role' => PersonRole::Child, 'birthday' => '2019-05-01']);
        Person::factory()->for($household)->create(['name' => 'Mama', 'role' => PersonRole::Adult, 'position' => 1]);
        Person::factory()->for($household)->create(['name' => 'Anna', 'role' => PersonRole::Child, 'birthday' => '2014-02-10']);
        Person::factory()->for($household)->create(['name' => 'Ohne', 'role' => PersonRole::Child, 'birthday' => null]);

        $this->assertSame(
            ['Anna', 'Ben', 'Ohne', 'Mama', 'Papa'],
            Person::query()->ordered()->pluck('name')->all(),
        );
    }
}
