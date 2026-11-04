<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConnectionStatus;
use App\Models\Household;
use App\Models\ProviderAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProviderAccount> */
final class ProviderAccountFactory extends Factory
{
    protected $model = ProviderAccount::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'provider' => 'google',
            'account_label' => fake()->unique()->safeEmail(),
            'access_token' => 'ya29.'.fake()->regexify('[A-Za-z0-9_-]{40}'),
            'refresh_token' => '1//'.fake()->regexify('[A-Za-z0-9_-]{40}'),
            'token_expires_at' => now()->addHour(),
            'status' => ConnectionStatus::Active,
        ];
    }

    public function expiredToken(): self
    {
        return $this->state(fn (): array => [
            'token_expires_at' => now()->subMinutes(10),
        ]);
    }

    public function invalidGrant(): self
    {
        return $this->state(fn (): array => [
            'status' => ConnectionStatus::InvalidGrant,
        ]);
    }
}
