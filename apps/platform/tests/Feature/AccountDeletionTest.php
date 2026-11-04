<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Household;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deleting one's own account: the household goes only where nobody else
 * manages it, and only after its name was typed.
 */
final class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create(['name' => 'Familie Huber']);
    }

    private function parent(): User
    {
        $person = Person::factory()->create(['household_id' => $this->household->id, 'permissions' => array_map(fn (Permission $p): string => $p->value, Permission::cases())]);

        return User::factory()->create(['household_id' => $this->household->id, 'person_id' => $person->id, 'password' => 'geheim123']);
    }

    public function test_the_last_manager_is_told_what_goes(): void
    {
        $this->actingAs($this->parent())->getJson('/api/auth/account')
            ->assertOk()
            ->assertJsonPath('households_lost.0.name', 'Familie Huber');
    }

    public function test_the_household_goes_only_with_its_typed_name(): void
    {
        $parent = $this->parent();

        $this->actingAs($parent)->deleteJson('/api/auth/account', ['password' => 'geheim123', 'confirm' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirm');
        $this->assertModelExists($this->household);

        $this->actingAs($parent)->deleteJson('/api/auth/account', ['password' => 'falsch', 'confirm' => ['Familie Huber']])
            ->assertJsonValidationErrors('password');

        $this->actingAs($parent)->deleteJson('/api/auth/account', ['password' => 'geheim123', 'confirm' => ['Familie Huber']])
            ->assertNoContent();

        $this->assertModelMissing($this->household);
        $this->assertModelMissing($parent);
    }

    public function test_with_another_manager_only_the_account_goes(): void
    {
        $parent = $this->parent();
        $this->parent();

        $this->actingAs($parent)->getJson('/api/auth/account')->assertJsonPath('households_lost', []);
        $this->actingAs($parent)->deleteJson('/api/auth/account', ['password' => 'geheim123', 'confirm' => []])
            ->assertNoContent();

        $this->assertModelMissing($parent);
        $this->assertModelExists($this->household);
    }

    public function test_works_while_read_only(): void
    {
        $this->household->forceFill(['read_only_since' => now()])->save();

        $this->actingAs($this->parent())->deleteJson('/api/auth/account', ['password' => 'geheim123', 'confirm' => ['Familie Huber']])
            ->assertNoContent();

        $this->assertModelMissing($this->household);
    }
}
