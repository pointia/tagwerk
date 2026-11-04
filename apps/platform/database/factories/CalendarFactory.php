<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Calendar;
use App\Models\Household;
use App\Models\ProviderAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Calendar> */
final class CalendarFactory extends Factory
{
    protected $model = Calendar::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'provider_account_id' => ProviderAccount::factory(),
            'external_id' => fake()->unique()->safeEmail(),
            'summary' => fake()->words(2, true),
            'color' => fake()->hexColor(),
            'timezone' => 'Europe/Vienna',
            'is_active' => true,
        ];
    }

    public function withWatchChannel(?\DateTimeInterface $expiresAt = null): self
    {
        return $this->state(fn (): array => [
            'push_channel_id' => fake()->uuid(),
            'push_resource_id' => fake()->regexify('[A-Za-z0-9]{20}'),
            'push_expires_at' => $expiresAt ?? now()->addDays(7),
        ]);
    }

    public function synced(string $token = 'CAESAA=='): self
    {
        return $this->state(fn (): array => [
            'sync_cursor' => $token,
            'last_synced_at' => now(),
        ]);
    }
}
