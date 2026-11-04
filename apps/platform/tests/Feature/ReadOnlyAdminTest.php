<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use App\Services\SupportAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin of a read-only household opens its notice and the profile,
 * nothing else — the operator helping it still everything.
 */
final class ReadOnlyAdminTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create(['read_only_since' => now()]);
    }

    public function test_family_pages_lead_to_the_notice(): void
    {
        $parent = User::factory()->create(['household_id' => $this->household->id]);

        $this->actingAs($parent)->get('/admin')->assertRedirect('/admin/read-only');
        $this->actingAs($parent)->get('/admin/people')->assertRedirect('/admin/read-only');
        $this->actingAs($parent)->get('/admin/read-only')
            ->assertSuccessful()
            ->assertSee('Dieser Haushalt ist schreibgeschützt.');
        $this->actingAs($parent)->get('/admin/profile')->assertSuccessful();
    }

    public function test_a_writable_household_has_no_notice(): void
    {
        $parent = User::factory()->create(['household_id' => Household::factory()->create()->id]);

        $this->actingAs($parent)->get('/admin/people')->assertSuccessful();
        $this->actingAs($parent)->get('/admin/read-only')->assertForbidden();
    }

    public function test_support_access_opens_everything(): void
    {
        $operator = User::factory()->create(['household_id' => null]);
        $operator->forceFill(['is_operator' => true])->save();

        $this->actingAs($operator)
            ->withSession([SupportAccessService::SESSION_KEY => $this->household->id])
            ->get('/admin/people')
            ->assertSuccessful();
    }
}
