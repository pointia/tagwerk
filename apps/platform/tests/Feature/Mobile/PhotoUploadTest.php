<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Enums\Feature;
use App\Models\Household;
use App\Models\Person;
use App\Models\Photo;
use App\Models\User;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Photos for the frame, sent from the phone. */
final class PhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->household = Household::factory()->create();
    }

    private function phone(bool $child = false): string
    {
        $person = $child
            ? Person::factory()->child()->for($this->household)->create()
            : Person::factory()->for($this->household)->create();

        return User::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $person->id,
        ])->createToken('phone')->plainTextToken;
    }

    private function jpeg(): UploadedFile
    {
        $canvas = imagecreatetruecolor(40, 30);
        ob_start();
        imagejpeg($canvas);

        return UploadedFile::fake()->createWithContent('IMG_20260801_101010.jpg', (string) ob_get_clean());
    }

    private function send(string $token, string $fingerprint, ?string $takenOn = '2026-08-01'): TestResponse
    {
        return $this->withToken($token)->post('/api/photos', [
            'photo' => $this->jpeg(),
            'fingerprint' => $fingerprint,
            'taken_on' => $takenOn,
        ], ['Accept' => 'application/json']);
    }

    public function test_a_parent_uploads_a_photo_with_its_day(): void
    {
        $response = $this->send($this->phone(), str_repeat('a', 64))->assertCreated();

        app(HouseholdContext::class)->setId((int) $this->household->id);
        $photo = Photo::query()->findOrFail($response->json('id'));

        $this->assertSame('2026-08-01', $photo->taken_on?->toDateString());
        $this->assertNotNull($photo->user_id);
        $this->assertStringEndsWith('.webp', $photo->path, 'Redrawn on the server, not stored as sent.');
        Storage::disk('local')->assertExists($photo->path);
    }

    /** Picking the same folder again sends only what is new. */
    public function test_the_same_original_is_stored_once(): void
    {
        $token = $this->phone();
        $fingerprint = str_repeat('b', 64);

        $first = $this->send($token, $fingerprint)->assertCreated()->json('id');
        $this->send($token, $fingerprint)->assertOk()->assertJson(['id' => $first, 'duplicate' => true]);

        $this->withToken($token)
            ->postJson('/api/photos/known', ['fingerprints' => [$fingerprint, str_repeat('c', 64)]])
            ->assertOk()
            ->assertExactJson(['known' => [$fingerprint]]);

        $this->assertSame(1, Photo::withoutGlobalScope('household')->count());
    }

    /** Fingerprints are per household: another family's photo is no duplicate here. */
    public function test_another_households_photo_does_not_count(): void
    {
        $fingerprint = str_repeat('d', 64);
        Photo::factory()->create(['fingerprint' => $fingerprint]);

        $this->withToken($this->phone())
            ->postJson('/api/photos/known', ['fingerprints' => [$fingerprint]])
            ->assertExactJson(['known' => []]);
    }

    public function test_children_do_not_upload_by_default(): void
    {
        $this->send($this->phone(child: true), str_repeat('e', 64))->assertForbidden();
    }

    public function test_without_the_photo_area_there_is_no_upload(): void
    {
        $this->household->update(['disabled_features' => [Feature::Photos->value]]);

        $this->send($this->phone(), str_repeat('f', 64))->assertNotFound();
    }

    public function test_what_is_no_picture_is_refused(): void
    {
        $this->withToken($this->phone())->post('/api/photos', [
            'photo' => UploadedFile::fake()->createWithContent('notes.txt', 'hello'),
            'fingerprint' => 'not-a-hash',
            'taken_on' => '2099-01-01',
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo', 'fingerprint', 'taken_on']);
    }
}
