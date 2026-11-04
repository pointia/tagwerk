<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\Calendar;
use App\Models\Event;
use App\Models\Household;
use App\Models\ProviderAccount;
use Database\Seeders\CalendarSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CalendarSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_running_twice_replaces_the_sample_data(): void
    {
        Household::factory()->create();

        $this->seed(CalendarSeeder::class);
        $events = Event::withoutHouseholdScope()->count();

        $this->seed(CalendarSeeder::class);

        $sample = ProviderAccount::withoutHouseholdScope()->where('provider', 'google')->sole();

        $this->assertSame(1, Calendar::withoutHouseholdScope()->where('provider_account_id', $sample->id)->count());
        $this->assertSame($events, Event::withoutHouseholdScope()->count());
    }
}
