<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Households\Pages\EditHousehold;
use App\Integrations\Geocoding\Nominatim\NominatimGeocoder;
use App\Integrations\Geocoding\TimezoneAtLocation;
use App\Integrations\ProviderRegistry;
use App\Models\Household;
use App\Models\User;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

final class GeocodingTest extends TestCase
{
    use RefreshDatabase;

    private const RESULTS = [
        [
            'lat' => '48.30639',
            'lon' => '14.28611',
            'display_name' => 'Linz, Oberösterreich, Österreich',
            'address' => ['country_code' => 'at'],
        ],
        [
            'lat' => '51.31667',
            'lon' => '12.41667',
            'display_name' => 'Linz, Sachsen, Deutschland',
            'address' => ['country_code' => 'de'],
        ],
    ];

    public function test_an_address_becomes_coordinates(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response(self::RESULTS)]);

        $suggestions = app(NominatimGeocoder::class)->search('Linz');

        // Several places share a name — whoever types makes the choice.
        $this->assertCount(2, $suggestions);
        $this->assertSame('Linz, Sachsen, Deutschland', $suggestions[1]->label);

        // Rounded to about a kilometre: enough for sun and weather.
        $this->assertSame(48.31, $suggestions[0]->latitude);
        $this->assertSame(14.29, $suggestions[0]->longitude);
        $this->assertSame('Linz, Oberösterreich, Österreich', $suggestions[0]->label);

        // The time zone comes along — there is no separate field for it anymore.
        $this->assertSame('Europe/Vienna', $suggestions[0]->timezone);
        $this->assertSame('Europe/Berlin', $suggestions[1]->timezone);
    }

    public function test_with_several_zones_the_nearest_wins(): void
    {
        $zones = app(TimezoneAtLocation::class);

        // The USA has dozens; New York and Los Angeles are clear-cut.
        $this->assertSame('America/New_York', $zones->forCountry('us', 40.7, -74.0));
        $this->assertSame('America/Los_Angeles', $zones->forCountry('us', 34.0, -118.2));

        // Austria has exactly one, and a made-up country none at all.
        $this->assertSame('Europe/Vienna', $zones->forCountry('at', 48.3, 14.3));
        $this->assertNull($zones->forCountry('zz', 0.0, 0.0));
    }

    public function test_without_results_nothing_is_returned(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);

        $this->assertSame([], app(NominatimGeocoder::class)->search('Hgfdsa'));
    }

    public function test_an_empty_field_does_not_even_ask(): void
    {
        Http::preventStrayRequests();

        $this->assertSame([], app(NominatimGeocoder::class)->search('   '));
    }

    public function test_a_service_outage_has_no_consequences(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('', 503)]);

        $this->assertSame([], app(NominatimGeocoder::class)->search('Linz'));
    }

    public function test_the_geocoder_works_without_credentials(): void
    {
        $geocoder = app(ProviderRegistry::class)->geocoder();

        $this->assertInstanceOf(NominatimGeocoder::class, $geocoder);
    }

    private const SCHOENAU = [
        'lat' => '48.39712',
        'lon' => '14.73891',
        'display_name' => 'Dorfstraße 3, Schönau im Mühlkreis, Bezirk Freistadt, Oberösterreich, 4274, Österreich',
        'address' => [
            'road' => 'Dorfstraße',
            'house_number' => '3',
            'village' => 'Schönau im Mühlkreis',
            'postcode' => '4274',
            'country_code' => 'at',
        ],
    ];

    public function test_a_suggestion_brings_the_address_parts(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([self::SCHOENAU])]);

        $place = app(NominatimGeocoder::class)->search('Dorfstraße 3 Schönau')[0];

        $this->assertSame('Dorfstraße 3', $place->street);
        $this->assertSame('4274', $place->postalCode);
        // A village is "village" at Nominatim, a town "city" — both are the place.
        $this->assertSame('Schönau im Mühlkreis', $place->city);
        $this->assertSame('AT', $place->countryCode);

        // About a kilometre: the valley's weather, not the next ridge's.
        $this->assertSame(48.4, $place->latitude);
        $this->assertSame(14.74, $place->longitude);
    }

    private function editForm(): Testable
    {
        $household = Household::factory()->create([
            'address' => null,
            'street' => null,
            'postal_code' => null,
            'city' => null,
            'country_code' => null,
            'latitude' => null,
            'longitude' => null,
            'timezone' => 'UTC',
        ]);
        $this->actingAs(User::factory()->create(['household_id' => $household->id]));
        app(HouseholdContext::class)->setId($household->id);

        return Livewire::test(EditHousehold::class, ['record' => $household->getRouteKey()]);
    }

    public function test_a_chosen_suggestion_fills_the_address_fields(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([self::SCHOENAU])]);

        $key = json_encode(get_object_vars(app(NominatimGeocoder::class)->search('Schönau')[0]), JSON_THROW_ON_ERROR);

        $this->editForm()
            ->set('data.address_lookup', $key)
            ->assertFormSet([
                'street' => 'Dorfstraße 3',
                'postal_code' => '4274',
                'city' => 'Schönau im Mühlkreis',
                'country_code' => 'AT',
                'latitude' => 48.4,
                'longitude' => 14.74,
                'address' => 'Dorfstraße 3, 4274 Schönau im Mühlkreis',
                'timezone' => 'Europe/Vienna',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $household = Household::query()->sole();

        $this->assertSame('Dorfstraße 3', $household->street);
        $this->assertSame('AT', $household->country_code);
        $this->assertSame(14.74, (float) $household->longitude);
        $this->assertSame('Europe/Vienna', $household->timezone);
    }

    /** A new house the map does not know yet: typed by hand, located anyway. */
    public function test_an_address_typed_by_hand_is_located(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([self::SCHOENAU])]);

        $this->editForm()
            ->fillForm([
                'street' => 'Neubau 7',
                'postal_code' => '4274',
                'country_code' => 'AT',
            ])
            ->set('data.city', 'Schönau im Mühlkreis')
            ->assertFormSet([
                'street' => 'Neubau 7',
                'latitude' => 48.4,
                'longitude' => 14.74,
                'address' => 'Neubau 7, 4274 Schönau im Mühlkreis',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'countrycodes=at')
            && str_contains(urldecode($request->url()), 'city=Schönau im Mühlkreis'));
    }

    /** Without an address the household cannot be saved — the weather needs it. */
    public function test_the_address_is_required(): void
    {
        $this->editForm()
            ->call('save')
            ->assertHasFormErrors(['street', 'postal_code', 'city', 'country_code']);
    }
}
