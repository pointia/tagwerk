<?php

declare(strict_types=1);

namespace Tests\Feature\Wall;

use App\Models\Household;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class WallWeatherTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create([
            'latitude' => 48.2,
            'longitude' => 16.4,
        ]);

        app(HouseholdContext::class)->setId((int) $this->household->id);

        $device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        $this->token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];
    }

    /** @param array<string, mixed> $data */
    private function respond(array $data = []): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response([
            'current' => ['temperature_2m' => 17.4, 'weather_code' => 61, 'is_day' => 1],
            'daily' => ['time' => ['2026-09-29'], 'temperature_2m_max' => [21.2], 'temperature_2m_min' => [11.8]],
            ...$data,
        ])]);
    }

    /*
     * The next three days under today: whether the weekend trip stays dry is
     * read in passing, too. Today is the first daily entry and stays out.
     */
    public function test_the_next_three_days_come_along(): void
    {
        $this->respond(['daily' => [
            'time' => ['2026-09-29', '2026-09-30', '2026-10-01', '2026-10-02'],
            'temperature_2m_max' => [21.2, 18.6, 14.1, 16.0],
            'temperature_2m_min' => [11.8, 9.4, 7.2, 8.9],
            'weather_code' => [61, 3, 71, 0],
        ]]);

        $this->withToken($this->token)
            ->getJson('/api/wall/weather')
            ->assertOk()
            ->assertJsonPath('weather.max', 21)
            ->assertJsonCount(3, 'weather.days')
            ->assertJsonPath('weather.days.0.date', '2026-09-30')
            ->assertJsonPath('weather.days.0.max', 19)
            ->assertJsonPath('weather.days.0.min', 9)
            ->assertJsonPath('weather.days.1.label', 'Schnee')
            ->assertJsonPath('weather.today.date', '2026-09-29')
            ->assertJsonPath('weather.days.2.date', '2026-10-02');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'forecast_days=4')
            && str_contains(urldecode($request->url()), 'weather_code'));
    }

    public function test_no_access_without_token(): void
    {
        $this->getJson('/api/wall/weather')->assertUnauthorized();
    }

    public function test_the_weather_comes_with_symbol_and_label(): void
    {
        $this->respond();

        $this->withToken($this->token)
            ->getJson('/api/wall/weather')
            ->assertOk()
            ->assertJsonPath('weather.current', 17)
            ->assertJsonPath('weather.max', 21)
            ->assertJsonPath('weather.min', 12)
            ->assertJsonPath('weather.label', 'Regen')
            ->assertJsonPath('weather.symbol', '🌧️')
            ->assertJsonPath('hint', null);
    }

    /** A kiosk that redraws every 30 seconds must not ask every 30 seconds. */
    public function test_the_second_request_does_not_go_out(): void
    {
        $this->respond();

        $this->withToken($this->token)->getJson('/api/wall/weather')->assertOk();
        $this->withToken($this->token)->getJson('/api/wall/weather')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_without_location_there_is_a_sentence_instead_of_an_error(): void
    {
        $this->household->update(['latitude' => null, 'longitude' => null]);

        $this->withToken($this->token)
            ->getJson('/api/wall/weather')
            ->assertOk()
            ->assertJsonPath('weather', null)
            ->assertJsonPath('hint', 'Für das Wetter fehlt der Ort des Haushalts. Er steht in der Verwaltung unter „Haushalt".');
    }

    public function test_a_provider_outage_is_not_a_failure(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response('', 503)]);

        $this->withToken($this->token)
            ->getJson('/api/wall/weather')
            ->assertOk()
            ->assertJsonPath('weather', null)
            ->assertJsonPath('hint', 'Das Wetter ist gerade nicht erreichbar.');
    }

    /**
     * @param  list<int>  $codes  one per hour from midnight
     * @return array<string, mixed>
     */
    private function hourly(string $date, array $codes, int $rain = 0): array
    {
        $hours = array_keys($codes);

        return [
            'time' => array_map(static fn (int $hour): string => sprintf('%sT%02d:00', $date, $hour), $hours),
            'temperature_2m' => array_map(static fn (int $hour): float => 10.0 + $hour / 2, $hours),
            'weather_code' => $codes,
            'precipitation_probability' => array_fill(0, count($codes), $rain),
            'is_day' => array_map(static fn (int $hour): int => $hour >= 7 && $hour < 19 ? 1 : 0, $hours),
        ];
    }

    /*
     * Open-Meteo's own daily code is the worst hour. One cloudy hour at dawn
     * made a sunny day "cloudy" — the complaint that started this. The
     * daylight hours decide instead.
     */
    public function test_a_sunny_day_with_a_cloudy_morning_stays_sunny(): void
    {
        $codes = [...array_fill(0, 7, 3), 3, 3, ...array_fill(0, 10, 0), ...array_fill(0, 5, 3)];

        $this->respond([
            'daily' => [
                'time' => ['2026-09-29'],
                'temperature_2m_max' => [21.2],
                'temperature_2m_min' => [11.8],
                'weather_code' => [3],
            ],
            'hourly' => $this->hourly('2026-09-29', $codes),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/wall/weather')
            ->assertJsonPath('weather.today.label', 'Klar')
            ->assertJsonPath('weather.today.symbol', '☀️');
    }

    /** A shower over a quarter of the day outweighs the sun around it. */
    public function test_rain_in_a_quarter_of_the_day_decides_the_day(): void
    {
        $codes = [...array_fill(0, 7, 0), 61, 61, 61, ...array_fill(0, 14, 0)];

        $this->respond([
            'daily' => [
                'time' => ['2026-09-29'],
                'temperature_2m_max' => [21.2],
                'temperature_2m_min' => [11.8],
                'weather_code' => [61],
            ],
            'hourly' => $this->hourly('2026-09-29', $codes),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/wall/weather')
            ->assertJsonPath('weather.today.label', 'Regen');
    }

    /*
     * Today only from the current hour on: 08:00 is no forecast at noon. The
     * cache holds an hour, so this is cut at answer time.
     */
    public function test_today_lists_the_hours_still_to_come(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-29 14:20', 'Europe/Vienna'));

        $this->respond([
            'timezone' => 'Europe/Vienna',
            'daily' => [
                'time' => ['2026-09-29', '2026-09-30'],
                'temperature_2m_max' => [21.2, 18.0],
                'temperature_2m_min' => [11.8, 9.0],
                'weather_code' => [0, 0],
            ],
            'hourly' => [
                ...$this->hourly('2026-09-29', array_fill(0, 24, 0), 20),
            ],
        ]);

        $response = $this->withToken($this->token)->getJson('/api/wall/weather')->assertOk();

        $this->assertSame('14:00', $response->json('weather.today.hours.0.time'));
        $this->assertCount(10, $response->json('weather.today.hours'));
        $this->assertSame(20, $response->json('weather.today.hours.0.rain'));
        $this->assertSame('🌙', $response->json('weather.today.hours.9.symbol'));
    }

    /** The unit follows the household's place: Ohio reads Fahrenheit. */
    public function test_a_household_in_the_united_states_gets_fahrenheit(): void
    {
        $this->household->update(['timezone' => 'America/New_York']);
        $this->respond();

        $this->withToken($this->token)
            ->getJson('/api/wall/weather')
            ->assertJsonPath('weather.unit', 'F');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'temperature_unit=fahrenheit'));
    }

    public function test_a_household_in_austria_gets_celsius(): void
    {
        $this->respond();

        $this->withToken($this->token)
            ->getJson('/api/wall/weather')
            ->assertJsonPath('weather.unit', 'C');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'temperature_unit=celsius'));
    }

    /*
     * High veil clouds count as "overcast" in the WMO code although the sun
     * shines through: eight of eleven hours of sunshine is no cloudy day.
     */
    public function test_sunshine_through_veil_clouds_is_not_cloudy(): void
    {
        $this->respond([
            'daily' => [
                'time' => ['2026-09-29'],
                'temperature_2m_max' => [19.2],
                'temperature_2m_min' => [9.9],
                'weather_code' => [3],
                'sunshine_duration' => [8 * 3600],
                'daylight_duration' => [11 * 3600],
            ],
            'hourly' => $this->hourly('2026-09-29', array_fill(0, 24, 3)),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/wall/weather')
            ->assertJsonPath('weather.today.label', 'Überwiegend klar')
            ->assertJsonPath('weather.today.sunshine', 8);
    }
}
