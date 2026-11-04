<?php

declare(strict_types=1);

namespace Tests\Feature\Wall;

use App\Enums\Permission;
use App\Models\Household;
use App\Models\Person;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Models\User;
use App\Services\DevicePairingService;
use App\Services\HouseholdExport;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Favourites, albums, memories and showing a picture on the walls. */
final class PhotoCollectionsTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->household = Household::factory()->create(['timezone' => 'Europe/Vienna']);
        app(HouseholdContext::class)->setId((int) $this->household->id);

        $device = app(DevicePairingService::class)->createCode($this->household, 'Vorzimmer');
        $this->token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];
    }

    private function account(bool $manager = true, ?Household $household = null): User
    {
        $household ??= $this->household;
        $person = Person::factory()->for($household)->create(
            $manager ? [] : ['permissions' => [Permission::PhotosUpload->value]],
        );

        return User::factory()->create(['household_id' => $household->id, 'person_id' => $person->id]);
    }

    /** @param  array<string, mixed>  $extra */
    private function photo(array $extra = []): Photo
    {
        return Photo::factory()->create(['household_id' => $this->household->id, ...$extra]);
    }

    /** @return list<string> */
    private function wallIds(string $query = ''): array
    {
        Auth::forgetGuards();

        return array_column(
            $this->withToken($this->token)->getJson('/api/wall/photos'.$query)->assertOk()->json('photos'),
            'id',
        );
    }

    public function test_the_wall_hearts_a_photo_and_a_frame_shows_only_favourites(): void
    {
        $liked = $this->photo();
        $other = $this->photo();

        $this->withToken($this->token)
            ->putJson("/api/wall/photos/{$liked->id}/favorite", ['favorite' => true])
            ->assertOk()
            ->assertJson(['favorite' => true]);

        $this->assertSame([(string) $liked->id], $this->wallIds('?from=favorites'));
        $this->assertEqualsCanonicalizing([(string) $liked->id, (string) $other->id], $this->wallIds());
    }

    public function test_an_album_holds_photos_and_a_frame_draws_from_it(): void
    {
        $parent = $this->account();
        $inside = $this->photo();
        $this->photo();

        $album = $this->actingAs($parent)->postJson('/api/photos/albums', ['name' => 'Oma & Opa'])
            ->assertCreated()
            ->json('id');

        $this->actingAs($parent)->postJson("/api/photos/albums/{$album}/add", ['ids' => [$inside->id]])
            ->assertOk()
            ->assertJson(['count' => 1]);

        $this->assertSame([(string) $inside->id], $this->wallIds("?from=albums&albums[]={$album}"));

        $albums = $this->withToken($this->token)->getJson('/api/wall/photos/albums')->assertOk()->json('albums');
        $this->assertSame('Oma & Opa', $albums[0]['name']);
        $this->assertSame(1, $albums[0]['count']);

        // The grid narrows the same way.
        $this->assertSame([$inside->id], array_column(
            $this->actingAs($parent)->getJson("/api/photos?album={$album}")->assertOk()->json('photos'),
            'id',
        ));

        $this->actingAs($parent)->postJson("/api/photos/albums/{$album}/remove", ['ids' => [$inside->id]])
            ->assertJson(['count' => 0]);
        $this->actingAs($parent)->deleteJson("/api/photos/albums/{$album}")->assertNoContent();
        $this->assertModelExists($inside);
    }

    public function test_a_photo_from_another_household_never_joins_an_album(): void
    {
        $parent = $this->account();
        $album = PhotoAlbum::query()->create(['name' => 'Urlaub']);

        $foreign = Household::factory()->create();
        $theirs = Photo::factory()->create(['household_id' => $foreign->id]);

        $this->actingAs($parent)->postJson("/api/photos/albums/{$album->id}/add", ['ids' => [$theirs->id]])
            ->assertJson(['count' => 0]);
    }

    public function test_only_who_may_delete_a_photo_may_name_it(): void
    {
        $uploader = $this->account(manager: false);
        $mine = $this->photo(['user_id' => $uploader->id]);
        $theirs = $this->photo();

        $this->actingAs($uploader)->patchJson("/api/photos/{$mine->id}", ['caption' => '  Sommer am See '])
            ->assertOk()
            ->assertJson(['caption' => 'Sommer am See']);

        $this->actingAs($uploader)->patchJson("/api/photos/{$theirs->id}", ['caption' => 'Fremd'])
            ->assertForbidden();
    }

    public function test_the_grid_follows_the_day_taken_and_says_favourite(): void
    {
        $parent = $this->account();
        $old = $this->photo(['taken_on' => '2020-07-14', 'favorite' => true]);
        $recent = $this->photo(['taken_on' => '2026-10-01']);

        $photos = $this->actingAs($parent)->getJson('/api/photos')->assertOk()->json('photos');

        $this->assertSame([$recent->id, $old->id], array_column($photos, 'id'));
        $this->assertTrue($photos[1]['favorite']);

        $this->assertSame([$old->id], array_column(
            $this->actingAs($parent)->getJson('/api/photos?favorites=1')->json('photos'),
            'id',
        ));
    }

    public function test_memories_gather_the_same_days_in_earlier_years_and_busy_runs_of_days(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00', 'Europe/Vienna'));

        foreach (['2023-10-25', '2023-10-26', '2023-10-28'] as $day) {
            $this->photo(['taken_on' => $day]);
        }

        // Only two from that year: no story.
        $this->photo(['taken_on' => '2024-10-26']);
        $this->photo(['taken_on' => '2024-10-27']);

        // A holiday: nine pictures over three days in a row.
        foreach (range(0, 8) as $index) {
            $this->photo(['taken_on' => CarbonImmutable::parse('2025-07-14')->addDays($index % 3)->toDateString()]);
        }

        $memories = $this->withToken($this->token)->getJson('/api/wall/photos/memories')->assertOk()->json('memories');

        $this->assertSame(['on_this_day', 'days'], array_column($memories, 'kind'));
        $this->assertSame(3, $memories[0]['years']);
        $this->assertSame(3, $memories[0]['count']);
        $this->assertSame(['2025-07-14', '2025-07-16'], [$memories[1]['from'], $memories[1]['until']]);
        $this->assertSame(9, $memories[1]['count']);
    }

    public function test_a_phone_shows_a_photo_on_the_walls_only(): void
    {
        $parent = $this->account();
        $photo = $this->photo(['caption' => 'Geburtstag']);

        $this->actingAs($parent)->postJson("/api/photos/{$photo->id}/show")
            ->assertOk()
            ->assertJson(['minutes' => 10]);

        $range = '?from=2026-10-26&to=2026-10-26';

        Auth::forgetGuards();
        $shown = $this->withToken($this->token)->getJson('/api/wall/agenda'.$range)->assertOk()->json('shown_photo');
        $this->assertSame((string) $photo->id, $shown['photo']['id']);
        $this->assertSame('Geburtstag', $shown['photo']['caption']);

        Auth::forgetGuards();
        $this->assertNull($this->actingAs($parent)->getJson('/api/wall/agenda'.$range)->json('shown_photo'));
    }

    public function test_frame_preferences_are_kept_and_resting_is_the_tablets(): void
    {
        $this->withToken($this->token)->putJson('/api/preferences', [
            'photo_style' => 'mosaic',
            'photo_rest_style' => 'ambient',
            'photo_source' => 'albums',
            'photo_albums' => [3, 5],
        ])->assertOk()
            ->assertJsonPath('preferences.photo_style', 'mosaic')
            ->assertJsonPath('preferences.photo_rest_style', 'ambient')
            ->assertJsonPath('preferences.photo_albums', [3, 5]);

        $this->withToken($this->token)->putJson('/api/preferences', ['photo_style' => 'carousel'])
            ->assertUnprocessable();

        Auth::forgetGuards();
        $this->actingAs($this->account())->putJson('/api/preferences', ['photo_rest_style' => 'duo'])
            ->assertUnprocessable();
    }

    public function test_the_export_carries_albums_with_their_photos(): void
    {
        $photo = $this->photo();
        $album = PhotoAlbum::query()->create(['name' => 'Urlaub']);
        $album->photos()->attach($photo->id, ['created_at' => now()]);

        $tables = app(HouseholdExport::class)->export($this->household)['tables'];

        $this->assertCount(1, $tables['photo_album_photo']);
        $this->assertCount(1, $tables['photo_albums']);
    }
}
