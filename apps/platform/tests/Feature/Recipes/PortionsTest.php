<?php

declare(strict_types=1);

namespace Tests\Feature\Recipes;

use App\Enums\Gender;
use App\Models\Household;
use App\Models\MealPlanEntry;
use App\Models\Person;
use App\Models\User;
use App\Services\PortionFactor;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Portions follow from who eats: age and, if stated, gender.
 */
final class PortionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_adult_man_is_the_measure(): void
    {
        $this->assertSame(1.0, PortionFactor::factor(30, Gender::Male));
        $this->assertSame(0.78, PortionFactor::factor(30, Gender::Female));
        // Without a gender the mean of both.
        $this->assertSame(0.89, PortionFactor::factor(30, null));
        $this->assertSame(0.48, PortionFactor::factor(2, Gender::Female));
        $this->assertSame(1.13, PortionFactor::factor(16, Gender::Male));
    }

    public function test_without_a_birthday_a_person_counts_as_an_adult(): void
    {
        $household = Household::factory()->create();
        app(HouseholdContext::class)->setId((int) $household->id);

        $person = Person::factory()->create(['household_id' => $household->id, 'birthday' => null, 'gender' => null]);

        $this->assertSame(0.89, app(PortionFactor::class)->for($person));
    }

    public function test_the_age_is_taken_on_the_day_of_the_meal(): void
    {
        $household = Household::factory()->create();
        app(HouseholdContext::class)->setId((int) $household->id);

        $person = Person::factory()->create([
            'household_id' => $household->id,
            'birthday' => '2022-06-01',
            'gender' => Gender::Male,
        ]);

        $this->assertSame(0.52, app(PortionFactor::class)->for($person, CarbonImmutable::parse('2026-05-31')));
        $this->assertSame(0.61, app(PortionFactor::class)->for($person, CarbonImmutable::parse('2026-06-01')));
    }

    public function test_a_planned_meal_counts_its_eaters(): void
    {
        $household = Household::factory()->create();
        app(HouseholdContext::class)->setId((int) $household->id);

        $dad = Person::factory()->create(['household_id' => $household->id, 'birthday' => '1985-01-01', 'gender' => Gender::Male]);
        $mum = Person::factory()->create(['household_id' => $household->id, 'birthday' => '1987-01-01', 'gender' => Gender::Female]);
        $kid = Person::factory()->child()->create(['household_id' => $household->id, 'birthday' => '2020-03-01', 'gender' => Gender::Male]);

        $token = User::factory()->create(['household_id' => $household->id, 'person_id' => $dad->id])
            ->createToken('phone')->plainTextToken;

        $everyone = $this->withToken($token)->postJson('/api/wall/meals', [
            'planned_on' => '2026-10-07',
            'kind' => 'dinner',
            'title' => 'Kaiserschmarrn',
        ])->assertCreated()
            ->assertJsonPath('eaters', null)
            // 1.0 + 0.78 + 0.61 (a boy of six)
            ->assertJsonPath('servings', 2.4);

        $this->withToken($token)->patchJson("/api/wall/meals/{$everyone->json('id')}", ['eaters' => [$mum->id, $kid->id]])
            ->assertOk()
            ->assertJsonPath('eaters', [$mum->id, $kid->id])
            ->assertJsonPath('servings', 1.4);

        // All of them again is everyone — someone born later eats along.
        $this->withToken($token)->patchJson("/api/wall/meals/{$everyone->json('id')}", ['eaters' => [$kid->id, $mum->id, $dad->id]])
            ->assertJsonPath('eaters', null);

        $this->withToken($token)->patchJson("/api/wall/meals/{$everyone->json('id')}", ['eaters' => [999999]])
            ->assertUnprocessable();

        $this->withToken($token)->getJson('/api/wall/meals?from=2026-10-05&to=2026-10-11')
            ->assertJsonPath('meals.0.servings', 2.4)
            ->assertJsonCount(3, 'people');

        $this->assertNull(MealPlanEntry::query()->sole()->eaters);
    }

    /** Grandma visits; "everyone" stays the family until she is chosen. */
    public function test_a_guest_eats_along_only_when_chosen(): void
    {
        $household = Household::factory()->create();
        app(HouseholdContext::class)->setId((int) $household->id);

        $dad = Person::factory()->create(['household_id' => $household->id, 'birthday' => '1985-01-01', 'gender' => Gender::Male]);
        $grandma = Person::factory()->guest()->create(['household_id' => $household->id, 'birthday' => '1955-01-01', 'gender' => Gender::Female]);

        $token = User::factory()->create(['household_id' => $household->id, 'person_id' => $dad->id])
            ->createToken('phone')->plainTextToken;

        $meal = $this->withToken($token)->postJson('/api/wall/meals', [
            'planned_on' => '2026-10-07',
            'kind' => 'dinner',
            'title' => 'Kaiserschmarrn',
        ])->assertCreated()
            ->assertJsonPath('eaters', null)
            ->assertJsonPath('servings', 1);

        $this->withToken($token)->patchJson("/api/wall/meals/{$meal->json('id')}", ['eaters' => [$dad->id, $grandma->id]])
            ->assertOk()
            ->assertJsonPath('eaters', [$dad->id, $grandma->id]);

        // Only the family again is everyone.
        $this->withToken($token)->patchJson("/api/wall/meals/{$meal->json('id')}", ['eaters' => [$dad->id]])
            ->assertJsonPath('eaters', null);

        $this->withToken($token)->getJson('/api/wall/meals?from=2026-10-05&to=2026-10-11')
            ->assertJsonPath('people.1.guest', true);
    }
}
