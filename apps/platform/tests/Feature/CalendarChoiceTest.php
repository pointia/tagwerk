<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Calendars\Pages\EditCalendar;
use App\Filament\Resources\Calendars\Pages\ListCalendars;
use App\Filament\Resources\Devices\Pages\EditDevice;
use App\Integrations\Calendar\Builtin\BuiltinCalendarProvider;
use App\Models\Calendar;
use App\Models\Device;
use App\Models\Event;
use App\Models\Household;
use App\Models\Person;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The admin decides which calendars are shown at all; each account and each
 * wall then picks among them.
 */
final class CalendarChoiceTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private Calendar $work;

    private Calendar $family;

    /** @var array<int, int> Everything shown, including the household's built-in calendar. */
    private array $shown;

    private Calendar $off;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();
        app(HouseholdContext::class)->setId($this->household->id);

        $account = ProviderAccount::factory()->create([
            'household_id' => $this->household->id,
            'provider' => BuiltinCalendarProvider::KEY,
        ]);
        $calendar = fn (string $name, bool $active = true): Calendar => Calendar::factory()->create([
            'household_id' => $this->household->id,
            'provider_account_id' => $account->id,
            'summary' => $name,
            'is_active' => $active,
        ]);

        $this->work = $calendar('Arbeit');
        $this->family = $calendar('Schule');
        $this->off = $calendar('Aus', false);

        $this->shown = Calendar::query()->where('is_active', true)->orderBy('id')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        foreach ([$this->work, $this->family] as $entry) {
            Event::factory()->create([
                'household_id' => $this->household->id,
                'calendar_id' => $entry->id,
                'title' => "Termin {$entry->summary}",
                'starts_at' => now()->addHour(),
                'ends_at' => now()->addHours(2),
            ]);
        }
    }

    private function agenda(string $token): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson('/api/wall/agenda?from='.now()->toDateString().'&to='.now()->addDay()->toDateString());
    }

    public function test_an_account_chooses_among_the_shown_calendars(): void
    {
        $user = User::factory()->create(['household_id' => $this->household->id]);
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->getJson('/api/calendars/choice')
            ->assertOk()
            ->assertJsonCount(count($this->shown), 'calendars')
            ->assertJsonPath('calendars.0.summary', 'Arbeit')
            ->assertJsonPath('calendars.0.shown', true);

        $this->withToken($token)->putJson('/api/calendars/choice', ['hidden' => [$this->off->id]])
            ->assertUnprocessable();

        $this->withToken($token)->putJson('/api/calendars/choice', ['hidden' => [$this->work->id]])
            ->assertOk()
            ->assertJsonPath('calendars.0.shown', false);

        $agenda = $this->agenda($token)->assertOk();
        $this->assertNotContains('Arbeit', array_column($agenda->json('calendars'), 'summary'));
        $this->assertSame(['Termin Schule'], array_column($agenda->json('events'), 'title'));
    }

    public function test_a_wall_shows_what_its_device_form_says(): void
    {
        $pairing = app(DevicePairingService::class);
        $token = $pairing->redeem($pairing->createCode($this->household, 'Küche')->pairing_code)['token'];
        $device = Device::query()->where('name', 'Küche')->firstOrFail();

        $this->actingAs(User::factory()->create(['household_id' => $this->household->id]));
        Filament::setCurrentPanel('admin');

        Livewire::test(EditDevice::class, ['record' => $device->getRouteKey()])
            ->assertFormSet(['shown_calendar_ids' => $this->shown])
            ->fillForm(['shown_calendar_ids' => array_values(array_diff($this->shown, [$this->work->id]))])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$this->work->id], $device->fresh()?->hidden_calendar_ids);
        $this->assertSame(['Termin Schule'], array_column($this->agenda($token)->json('events'), 'title'));
    }

    public function test_the_overview_shows_owners_and_confirms_changes(): void
    {
        $lena = Person::factory()->create(['household_id' => $this->household->id, 'name' => 'Lena']);
        $this->work->people()->attach($lena);

        $this->actingAs(User::factory()->create(['household_id' => $this->household->id]));
        Filament::setCurrentPanel('admin');

        Livewire::test(ListCalendars::class)
            ->assertSee('Lena')
            ->call('updateTableColumnState', 'is_active', (string) $this->family->getKey(), false)
            ->assertNotified('Kalender „Schule“ ist ausgeblendet');

        $this->assertFalse($this->family->fresh()?->is_active);

        Livewire::test(EditCalendar::class, ['record' => $this->work->getRouteKey()])
            ->fillForm(['color' => '#ff0000'])
            ->call('save')
            ->assertNotified('Kalender gespeichert');

        $this->assertSame('#ff0000', $this->work->fresh()?->color);
    }
}
