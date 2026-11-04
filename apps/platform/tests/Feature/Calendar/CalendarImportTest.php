<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Models\Calendar;
use App\Models\Household;
use App\Models\ProviderAccount;
use App\Services\Calendar\CalendarImportService;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CalendarImportTest extends TestCase
{
    use RefreshDatabase;

    private ProviderAccount $source;

    protected function setUp(): void
    {
        parent::setUp();

        $household = Household::factory()->create();
        app(HouseholdContext::class)->setId((int) $household->id);

        $this->source = ProviderAccount::factory()->create([
            'household_id' => $household->id,
        ]);
    }

    private function calendarList(): void
    {
        Http::fake([
            'www.googleapis.com/*' => Http::response([
                'items' => [
                    [
                        'id' => 'alex@example.test',
                        'summary' => 'Alex',
                        'backgroundColor' => '#1e88e5',
                        'timeZone' => 'Europe/Vienna',
                        'primary' => true,
                    ],
                    [
                        'id' => 'familie@group.calendar.google.com',
                        'summary' => 'Familie',
                        'backgroundColor' => '#8e24aa',
                        'timeZone' => 'Europe/Vienna',
                    ],
                ],
            ]),
        ]);
    }

    public function test_calendar_list_is_imported(): void
    {
        $this->calendarList();

        $count = app(CalendarImportService::class)->import($this->source);

        $this->assertSame(2, $count);
        $this->assertSame(2, $this->source->calendars()->count());

        $own = Calendar::where('external_id', 'alex@example.test')->firstOrFail();
        $this->assertSame('Alex', $own->summary);
        $this->assertSame('#1e88e5', $own->color);
        $this->assertTrue($own->is_primary);
        $this->assertNotNull($this->source->fresh()->synced_at);
    }

    public function test_deselected_calendar_stays_deselected(): void
    {
        Calendar::factory()->create([
            'household_id' => $this->source->household_id,
            'provider_account_id' => $this->source->id,
            'external_id' => 'familie@group.calendar.google.com',
            'summary' => 'Alter Name',
            'color' => '#123456',
            'is_active' => false,
        ]);

        $this->calendarList();

        app(CalendarImportService::class)->import($this->source);

        $calendar = Calendar::where('external_id', 'familie@group.calendar.google.com')
            ->firstOrFail();

        // The name is refreshed, the household's selection and colour are not.
        $this->assertSame('Familie', $calendar->summary);
        $this->assertFalse($calendar->is_active);
        $this->assertSame('#123456', $calendar->color);
        $this->assertSame(2, $this->source->calendars()->count());
    }
}
