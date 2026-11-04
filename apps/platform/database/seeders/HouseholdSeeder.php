<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DeviceKind;
use App\Enums\PersonRole;
use App\Models\Device;
use App\Models\Household;
use App\Models\Person;
use App\Models\User;
use App\Services\ParentPin;
use App\Support\HouseholdContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates a complete sample household: two adults with a login, two children
 * without one and a wall tablet.
 */
final class HouseholdSeeder extends Seeder
{
    public function run(): void
    {
        $household = Household::create([
            'name' => 'Familie Muster',
            'timezone' => 'Europe/Vienna',
            'holiday_region' => 'AT-4',
            'day_bonus' => 2,
        ]);

        app(HouseholdContext::class)->setId($household->id);

        $adults = [
            ['name' => 'Alex', 'color' => '#0284c7', 'email' => 'alex@example.test'],
            ['name' => 'Sam', 'color' => '#7c3aed', 'email' => 'sam@example.test'],
        ];

        foreach ($adults as $adult) {
            $person = Person::create([
                'name' => $adult['name'],
                'color' => $adult['color'],
                'role' => PersonRole::Adult,
            ]);

            $user = User::create([
                'name' => $adult['name'],
                'email' => $adult['email'],
                'password' => Hash::make('password'),
            ]);
            $user->memberships()->create(['household_id' => $household->id, 'person_id' => $person->id, 'accepted_at' => now()]);
            $user->forceFill(['email_verified_at' => now()])->save();

            // A PIN per parent, so the protected path is visible at all.
            // Without one everything on the tablet is open.
            app(ParentPin::class)->set($user, '1234');
        }

        // Children have colours of their own, but deliberately no login.
        Person::create([
            'name' => 'Max',
            'color' => '#059669',
            'role' => PersonRole::Child,
            'birthday' => '2016-04-11',
        ]);

        Person::create([
            'name' => 'Lena',
            'color' => '#d97706',
            'role' => PersonRole::Child,
            'birthday' => '2019-09-02',
        ]);

        Device::create([
            'name' => 'Küche',
            'kind' => DeviceKind::Wall,
        ]);
    }
}
