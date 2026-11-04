<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\ThemePreference;
use App\Models\Household;
use App\Models\User;
use App\Services\ThemeSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Light, dark or automatic in the admin panel.
 *
 * The same choice as on the phone (`theme_preference`) and the same schedule
 * as on the wall: "automatic" follows the household's times, not the
 * operating system.
 */
final class ThemeSwitcher extends Component
{
    public string $preference = 'auto';

    public string $theme = 'light';

    public ?string $nextChange = null;

    public function mount(): void
    {
        $this->sync();
    }

    public function choose(string $preference): void
    {
        $choice = ThemePreference::tryFrom($preference);

        abort_if($choice === null, 422);

        $this->user()->update(['theme_preference' => $choice]);
        $this->sync();
    }

    /** Called by the browser's timer at the scheduled change. */
    public function refresh(): void
    {
        $this->sync();
    }

    /**
     * @return array{preference: string, theme: 'light'|'dark', next_change: string|null}
     */
    public static function stateFor(User $user): array
    {
        $choice = $user->theme_preference ?? ThemePreference::Auto;
        $household = Household::query()->find($user->membership()?->household_id);

        // The operator without a household has no schedule to follow.
        if ($household === null) {
            return [
                'preference' => $choice->value,
                'theme' => $choice === ThemePreference::Dark ? 'dark' : 'light',
                'next_change' => null,
            ];
        }

        $state = app(ThemeSchedule::class)->state($household, $choice, CarbonImmutable::now());

        return [
            'preference' => $choice->value,
            'theme' => $state['theme'],
            'next_change' => $state['next_change']?->toIso8601String(),
        ];
    }

    public function render(): View
    {
        return view('livewire.theme-switcher');
    }

    private function sync(): void
    {
        $state = self::stateFor($this->user());

        $this->preference = $state['preference'];
        $this->theme = $state['theme'];
        $this->nextChange = $state['next_change'];
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
