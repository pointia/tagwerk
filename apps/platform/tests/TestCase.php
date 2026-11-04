<?php

namespace Tests;

use App\Models\Household;
use App\Models\User;
use App\Services\ParentPin;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Switches on areas that are off until a household opts in (`OptIn`). */
    protected function optIn(Household $household, string ...$areas): void
    {
        $household->update(['opted_in_features' => [...(array) $household->opted_in_features, ...$areas]]);
    }

    /**
     * A parent account with a PIN — the wall asks for it from then on. The
     * PIN belongs to an account, so a household only has one through a parent.
     */
    protected function parentWithPin(Household $household, string $pin = '1234'): User
    {
        $parent = User::factory()->create(['household_id' => $household->id]);

        app(ParentPin::class)->set($parent, $pin);

        return $parent;
    }
}
