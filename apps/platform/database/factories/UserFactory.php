<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use WeakMap;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /** @var WeakMap<User, array{household_id: int, person_id: int|null}>|null */
    private static ?WeakMap $joins = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * `create(['household_id' => …, 'person_id' => …])` is the short way to an
     * account in a household: the two become an accepted membership, which
     * is where an account belongs.
     */
    public function configure(): static
    {
        self::$joins ??= new WeakMap;

        return $this
            ->afterMaking(function (User $user): void {
                $household = $user->getAttribute('household_id');
                $person = $user->getAttribute('person_id');
                $user->offsetUnset('household_id');
                $user->offsetUnset('person_id');

                if ($household !== null) {
                    self::$joins[$user] = ['household_id' => (int) $household, 'person_id' => $person === null ? null : (int) $person];
                }
            })
            ->afterCreating(function (User $user): void {
                if (isset(self::$joins[$user])) {
                    $user->memberships()->create([...self::$joins[$user], 'accepted_at' => now()]);
                }
            });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
