<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\InvalidImage;
use App\Filament\Resources\People\Pages\EditPerson;
use App\Models\Household;
use App\Models\Person;
use App\Models\User;
use App\Services\AvatarImage;
use App\Support\HouseholdContext;
use GdImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Profile pictures are photos of children and are served publicly. So the
 * tests use files the way an attacker or a phone would send them — not only
 * clean test images.
 */
final class AvatarImageTest extends TestCase
{
    use RefreshDatabase;

    private AvatarImage $images;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->images = app(AvatarImage::class);
    }

    /** @param  callable(GdImage): void|null  $paint */
    private function jpeg(int $width, int $height, ?callable $paint = null): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 120, 200));

        if ($paint !== null) {
            $paint($image);
        }

        ob_start();
        imagejpeg($image, null, 90);

        return (string) ob_get_clean();
    }

    /** Inserts a segment right after the JPEG start (APP1 for EXIF, COM for comments). */
    private function withSegment(string $jpeg, string $marker, string $data): string
    {
        return substr($jpeg, 0, 2).$marker.pack('n', strlen($data) + 2).$data.substr($jpeg, 2);
    }

    /** Minimal EXIF block that carries only the orientation. */
    private function exifOrientation(int $value): string
    {
        $tiff = 'II*'."\0".pack('V', 8)
            .pack('v', 1)
            .pack('vvVvv', 0x0112, 3, 1, $value, 0)
            .pack('V', 0);

        return "Exif\0\0".$tiff;
    }

    public function test_photo_is_downscaled_to_a_square_and_stored_as_webp(): void
    {
        $path = $this->images->store($this->jpeg(1600, 1200));

        $this->assertMatchesRegularExpression('#^avatars/[0-9a-z]{26}\.webp$#', $path);

        $info = getimagesizefromstring(Storage::disk('public')->get($path));

        $this->assertSame([AvatarImage::EDGE, AvatarImage::EDGE, IMAGETYPE_WEBP], [$info[0], $info[1], $info[2]]);
    }

    public function test_small_image_is_not_upscaled(): void
    {
        $info = getimagesizefromstring($this->images->normalize($this->jpeg(200, 300)));

        $this->assertSame([200, 200], [$info[0], $info[1]]);
    }

    /** Location and camera data must never reach storage. */
    public function test_metadata_is_dropped(): void
    {
        $jpeg = $this->withSegment($this->jpeg(400, 400), "\xFF\xE1", $this->exifOrientation(1).'GPSLatitude 48.2082 GEHEIM');
        $jpeg = $this->withSegment($jpeg, "\xFF\xFE", 'Kommentar GEHEIM');

        $result = $this->images->normalize($jpeg);

        $this->assertStringNotContainsString('GEHEIM', $result);
        $this->assertStringNotContainsString('Exif', $result);
    }

    /** A valid image with appended PHP passes every type check — redrawn, nothing of it remains. */
    public function test_appended_code_disappears(): void
    {
        $polyglot = $this->jpeg(300, 300).'<?php system($_GET["c"]); ?>';

        $result = $this->images->normalize($polyglot);

        $this->assertStringNotContainsString('<?php', $result);
        $this->assertSame(IMAGETYPE_WEBP, getimagesizefromstring($result)[2]);
    }

    public function test_svg_with_script_is_rejected(): void
    {
        $this->expectException(InvalidImage::class);

        $this->images->normalize('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>');
    }

    public function test_text_disguised_as_image_is_rejected(): void
    {
        $this->expectException(InvalidImage::class);

        $this->images->normalize("\xFF\xD8\xFF\xE0<?php echo 'kein Bild'; ?>");
    }

    public function test_gif_is_rejected(): void
    {
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagegif($image);

        $this->expectException(InvalidImage::class);

        $this->images->normalize((string) ob_get_clean());
    }

    /** A tiny file claiming 20,000 × 20,000 pixels must never be decompressed. */
    public function test_decompression_bomb_is_rejected_before_decoding(): void
    {
        $ihdr = pack('NNCCCCC', 20000, 20000, 8, 2, 0, 0, 0);
        $png = "\x89PNG\r\n\x1a\n"
            .pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr))
            .pack('N', 0).'IEND'.pack('N', crc32('IEND'));

        $this->expectException(InvalidImage::class);
        $this->expectExceptionMessage('40 Megapixel');

        $this->images->normalize($png);
    }

    /** Shot in portrait, stored in landscape: the rotation must go into the pixels before EXIF is dropped. */
    public function test_exif_orientation_is_applied(): void
    {
        // Top half red, bottom blue. Rotated 90° clockwise this becomes blue on the left, red on the right.
        $jpeg = $this->jpeg(400, 200, function (GdImage $image): void {
            imagefilledrectangle($image, 0, 0, 399, 99, imagecolorallocate($image, 220, 20, 20));
        });

        $result = imagecreatefromstring($this->images->normalize($this->withSegment($jpeg, "\xFF\xE1", $this->exifOrientation(6))));

        $left = imagecolorsforindex($result, imagecolorat($result, 20, 100));
        $right = imagecolorsforindex($result, imagecolorat($result, 180, 100));

        $this->assertGreaterThan($left['red'], $left['blue'], 'left should be blue');
        $this->assertGreaterThan($right['blue'], $right['red'], 'right should be red');
    }

    public function test_broken_exif_data_does_not_prevent_the_photo(): void
    {
        $jpeg = $this->withSegment($this->jpeg(100, 100), "\xFF\xE1", "Exif\0\0MM\0*\xFF\xFF\xFF\xFFkaputt");

        $this->assertSame(IMAGETYPE_WEBP, getimagesizefromstring($this->images->normalize($jpeg))[2]);
    }

    public function test_replaced_image_is_deleted(): void
    {
        $household = Household::factory()->create();
        app(HouseholdContext::class)->setId((int) $household->id);

        Storage::disk('public')->put('avatars/alt.webp', 'x');
        $person = Person::factory()->create(['household_id' => $household->id, 'avatar_path' => 'avatars/alt.webp']);

        $person->update(['avatar_path' => 'avatars/neu.webp']);

        Storage::disk('public')->assertMissing('avatars/alt.webp');
    }

    public function test_image_disappears_with_the_person(): void
    {
        $household = Household::factory()->create();
        app(HouseholdContext::class)->setId((int) $household->id);

        Storage::disk('public')->put('avatars/weg.webp', 'x');
        $person = Person::factory()->create(['household_id' => $household->id, 'avatar_path' => 'avatars/weg.webp']);

        $person->delete();

        Storage::disk('public')->assertMissing('avatars/weg.webp');
    }

    /**
     * The whole path through the backend: what arrives there is the redrawn
     * WebP, not the original file. Square, because avatar() requires 1:1 on
     * the server and the browser crops to that beforehand.
     */
    public function test_backend_stores_the_redrawn_image(): void
    {
        Storage::fake('tmp-for-tests');

        $household = Household::factory()->create();
        app(HouseholdContext::class)->setId((int) $household->id);
        $this->actingAs(User::factory()->create(['household_id' => $household->id]));

        $person = Person::factory()->create(['household_id' => $household->id]);

        Livewire::test(EditPerson::class, ['record' => $person->getRouteKey()])
            ->set('data.avatar_path', [UploadedFile::fake()->image('alex.jpg', 900, 900)])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = (string) $person->fresh()->avatar_path;

        $this->assertStringEndsWith('.webp', $path);
        $this->assertSame(IMAGETYPE_WEBP, getimagesizefromstring(Storage::disk('public')->get($path))[2]);
    }

    public function test_backend_rejects_svg(): void
    {
        Storage::fake('tmp-for-tests');

        $household = Household::factory()->create();
        app(HouseholdContext::class)->setId((int) $household->id);
        $this->actingAs(User::factory()->create(['household_id' => $household->id]));

        $person = Person::factory()->create(['household_id' => $household->id]);

        Livewire::test(EditPerson::class, ['record' => $person->getRouteKey()])
            ->set('data.avatar_path', [UploadedFile::fake()->createWithContent(
                'shield.svg',
                '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            )])
            ->call('save')
            ->assertHasFormErrors(['avatar_path']);

        $this->assertNull($person->fresh()->avatar_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
