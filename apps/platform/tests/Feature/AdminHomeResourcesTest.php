<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Checklist;
use App\Models\Household;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin pages for tasks and lists.
 *
 * The forms were generated and then relieved of `household_id` by hand.
 * Without these tests nobody would notice the field coming back or the form
 * breaking along the way.
 */
final class AdminHomeResourcesTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(Household $household): void
    {
        $this->actingAs(User::factory()->create(['household_id' => $household->id]));
    }

    public function test_task_list_shows_own_tasks(): void
    {
        $household = Household::factory()->create();
        $this->signIn($household);

        Task::factory()->create([
            'household_id' => $household->id,
            'title' => 'Altpapier zur Sammelstelle',
        ]);

        $this->get('/admin/tasks')
            ->assertSuccessful()
            ->assertSee('Altpapier zur Sammelstelle');
    }

    public function test_task_list_hides_foreign_households(): void
    {
        $this->signIn(Household::factory()->create());

        Task::factory()->create([
            'household_id' => Household::factory()->create()->id,
            'title' => 'Altpapier zur Sammelstelle',
        ]);

        $this->get('/admin/tasks')
            ->assertSuccessful()
            ->assertDontSee('Altpapier zur Sammelstelle');
    }

    public function test_task_form_loads(): void
    {
        $this->signIn(Household::factory()->create());

        $this->get('/admin/tasks/create')->assertSuccessful();
    }

    public function test_list_admin_loads(): void
    {
        $household = Household::factory()->create();
        $this->signIn($household);

        Checklist::factory()->create([
            'household_id' => $household->id,
            'name' => 'Baumarkt',
        ]);

        $this->get('/admin/checklists')
            ->assertSuccessful()
            ->assertSee('Baumarkt');

        $this->get('/admin/checklists/create')->assertSuccessful();
    }
}
