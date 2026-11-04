<?php

declare(strict_types=1);

namespace Tests\Feature\Recipes;

use App\Models\Household;
use App\Models\MealPlanEntry;
use App\Models\Person;
use App\Models\Recipe;
use App\Models\RecipeFavorite;
use App\Models\User;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Favourites, the household's history and suggestions drawn from both.
 */
final class RecipeListsTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();
    }

    private function phone(?Household $household = null): string
    {
        $household ??= $this->household;
        $person = Person::factory()->child()->for($household)->create();

        return User::factory()->create([
            'household_id' => $household->id,
            'person_id' => $person->id,
        ])->createToken('phone')->plainTextToken;
    }

    private function recipe(string $title, array $attributes = []): Recipe
    {
        return Recipe::factory()->create(['household_id' => $this->household->id, 'title' => $title, ...$attributes]);
    }

    private function planned(Recipe $recipe, int $daysAgo): void
    {
        MealPlanEntry::factory()->withRecipe((string) $recipe->id, 'builtin')->create([
            'household_id' => $this->household->id,
            'title' => $recipe->title,
            'planned_on' => now()->subDays($daysAgo)->toDateString(),
        ]);
    }

    public function test_anyone_marks_a_favourite_and_finds_it_in_the_list(): void
    {
        $soup = $this->recipe('Linsensuppe');
        $this->recipe('Kaiserschmarrn');
        $token = $this->phone();

        $this->withToken($token)
            ->putJson("/api/wall/recipe-book/builtin/{$soup->id}/favorite")
            ->assertOk()
            ->assertJsonPath('favorite', true);

        $this->withToken($token)->getJson('/api/wall/recipe-book?list=favorites')
            ->assertOk()
            ->assertJsonCount(1, 'recipes')
            ->assertJsonPath('recipes.0.name', 'Linsensuppe')
            ->assertJsonPath('recipes.0.favorite', true);

        $this->withToken($token)->getJson("/api/wall/recipe-book/builtin/{$soup->id}")
            ->assertJsonPath('recipe.favorite', true);

        $this->withToken($token)->deleteJson("/api/wall/recipe-book/builtin/{$soup->id}/favorite")->assertOk();

        $this->withToken($token)->getJson('/api/wall/recipe-book?list=favorites')->assertJsonCount(0, 'recipes');
    }

    public function test_favourites_stay_in_their_household(): void
    {
        $soup = $this->recipe('Linsensuppe');
        $this->withToken($this->phone())->putJson("/api/wall/recipe-book/builtin/{$soup->id}/favorite")->assertOk();
        Auth::forgetGuards();

        $this->withToken($this->phone(Household::factory()->create()))
            ->putJson("/api/wall/recipe-book/builtin/{$soup->id}/favorite")
            ->assertNotFound();

        app(HouseholdContext::class)->forId($this->household->id, fn () => $this->assertSame(1, RecipeFavorite::query()->count()));
    }

    public function test_often_and_forgotten_come_from_what_was_planned(): void
    {
        $soup = $this->recipe('Linsensuppe');
        $pancake = $this->recipe('Kaiserschmarrn');
        $this->recipe('Nie gekocht');
        $this->planned($soup, 3);
        $this->planned($soup, 10);
        $this->planned($pancake, 90);
        $token = $this->phone();

        $this->withToken($token)->getJson('/api/wall/recipe-book?list=often')
            ->assertJsonCount(1, 'recipes')
            ->assertJsonPath('recipes.0.name', 'Linsensuppe');

        $this->withToken($token)->getJson('/api/wall/recipe-book?list=forgotten')
            ->assertJsonCount(1, 'recipes')
            ->assertJsonPath('recipes.0.name', 'Kaiserschmarrn');

        $this->withToken($token)->getJson('/api/wall/recipe-book?list=forgotten&q=suppe')
            ->assertJsonCount(0, 'recipes');
    }

    public function test_suggestions_skip_what_was_just_cooked_and_repeat_for_the_same_seed(): void
    {
        foreach (range(1, 12) as $n) {
            $this->recipe("Rezept {$n}");
        }

        $recent = $this->recipe('Gestern gekocht');
        $this->planned($recent, 1);
        $token = $this->phone();

        $first = $this->withToken($token)->getJson('/api/wall/recipe-book/suggestions?seed=7')
            ->assertOk()
            ->assertJsonCount(6, 'recipes')
            ->json('recipes.*.name');

        $this->assertNotContains('Gestern gekocht', $first);
        $this->assertSame($first, $this->withToken($token)->getJson('/api/wall/recipe-book/suggestions?seed=7')->json('recipes.*.name'));
        $this->assertNotSame($first, $this->withToken($token)->getJson('/api/wall/recipe-book/suggestions?seed=8')->json('recipes.*.name'));
    }

    public function test_the_own_list_shows_the_households_book_with_filters(): void
    {
        $this->recipe('Linsensuppe', ['vegetarian' => true]);
        $this->recipe('Gulasch', ['vegetarian' => false]);

        $this->withToken($this->phone())->getJson('/api/wall/recipe-book?list=own&vegetarian=1')
            ->assertOk()
            ->assertJsonCount(1, 'recipes')
            ->assertJsonPath('recipes.0.name', 'Linsensuppe')
            ->assertJsonPath('recipes.0.provider', 'builtin');
    }

    public function test_an_unknown_list_is_refused(): void
    {
        $this->withToken($this->phone())->getJson('/api/wall/recipe-book?list=everything')->assertUnprocessable();
    }
}
