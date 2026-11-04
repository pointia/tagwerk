<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ThemeMode;
use App\Enums\ThemePreference;
use App\Livewire\ThemeSwitcher;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminThemeSwitcherTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): User
    {
        $household = Household::factory()->create([
            'theme_mode' => ThemeMode::Fixed,
            'theme_light_at' => '07:00',
            'theme_dark_at' => '20:00',
            'timezone' => 'Europe/Vienna',
        ]);

        $user = User::factory()->create(['household_id' => $household->id]);
        $this->actingAs($user);

        return $user;
    }

    public function test_choice_is_stored_on_the_user(): void
    {
        $user = $this->signIn();

        Livewire::test(ThemeSwitcher::class)
            ->call('choose', 'dark')
            ->assertSet('preference', 'dark')
            ->assertSet('theme', 'dark')
            ->assertSet('nextChange', null);

        $this->assertSame(ThemePreference::Dark, $user->refresh()->theme_preference);
    }

    public function test_automatic_follows_the_household_schedule(): void
    {
        $this->signIn();
        $this->travelTo('2026-09-15 21:30:00 Europe/Vienna');

        Livewire::test(ThemeSwitcher::class)
            ->call('choose', 'auto')
            ->assertSet('theme', 'dark')
            ->assertSet('nextChange', '2026-09-16T07:00:00+02:00');
    }

    public function test_unknown_choice_is_rejected(): void
    {
        $this->signIn();

        Livewire::test(ThemeSwitcher::class)
            ->call('choose', 'purple')
            ->assertStatus(422);
    }

    public function test_admin_sets_the_theme_before_rendering(): void
    {
        $this->signIn()->update(['theme_preference' => ThemePreference::Dark]);

        $this->get('/admin')
            ->assertSuccessful()
            ->assertSee('localStorage.setItem("theme", \'dark\')', false)
            ->assertSee('aria-label="Erscheinungsbild"', false);
    }
}
