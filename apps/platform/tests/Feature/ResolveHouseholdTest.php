<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ResolveHouseholdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'household'])->get(
            '/_test/household',
            fn (): array => ['id' => app(HouseholdContext::class)->id()],
        );
    }

    public function test_context_comes_from_the_signed_in_user(): void
    {
        $household = Household::factory()->create();
        $user = User::factory()->create(['household_id' => $household->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/_test/household')
            ->assertOk()
            ->assertJson(['id' => $household->id]);
    }

    public function test_user_without_household_sets_no_context(): void
    {
        $user = User::factory()->create(['household_id' => null]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/_test/household')
            ->assertOk()
            ->assertJson(['id' => null]);
    }
}
