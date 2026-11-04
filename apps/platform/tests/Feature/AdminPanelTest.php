<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_is_reachable_for_signed_in_users(): void
    {
        $household = Household::factory()->create();
        $user = User::factory()->create(['household_id' => $household->id]);

        $this->actingAs($user)->get('/admin')->assertSuccessful();
    }

    public function test_panel_rejects_guests(): void
    {
        $this->get('/admin')->assertRedirect();
    }
}
