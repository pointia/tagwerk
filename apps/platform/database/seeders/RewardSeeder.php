<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Household;
use App\Models\Person;
use App\Models\Reward;
use App\Services\PointLedger;
use App\Support\HouseholdContext;
use Illuminate\Database\Seeder;

/**
 * Rewards and a starting balance.
 *
 * Balances are created as ledger entries, not as a set number — the sample
 * household should show the same mechanics as real operation.
 */
final class RewardSeeder extends Seeder
{
    public function run(): void
    {
        $household = Household::firstOrFail();

        app(HouseholdContext::class)->setId($household->id);

        $rewards = [
            ['name' => 'Eine Folge extra', 'cost' => 20, 'description' => 'Am Wochenende, nach dem Abendessen.'],
            ['name' => 'Kino aussuchen', 'cost' => 60, 'description' => 'Der nächste Familienfilm.'],
            ['name' => 'Freund:in übernachten', 'cost' => 100, 'description' => null],
        ];

        foreach ($rewards as $position => $reward) {
            Reward::create([...$reward, 'position' => $position]);
        }

        $ledger = app(PointLedger::class);

        foreach (['Max' => 45, 'Lena' => 30] as $name => $points) {
            $person = Person::query()->where('name', $name)->first();

            if ($person !== null) {
                $ledger->book((int) $person->id, $points, 'Startguthaben');
            }
        }
    }
}
