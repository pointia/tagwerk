<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Photos\Pages\ListPhotos;
use App\Models\Household;
use App\Models\Photo;
use App\Models\User;
use App\Support\HouseholdContext;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** The admin sees the phone's trash too, and can restore or empty it. */
final class AdminPhotoTrashTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->household = Household::factory()->create();
        $this->actingAs(User::factory()->create(['household_id' => $this->household->id]));
        app(HouseholdContext::class)->setId((int) $this->household->id);
    }

    public function test_the_trash_filter_shows_deleted_photos_to_restore_or_purge(): void
    {
        $kept = Photo::factory()->for($this->household)->create();
        $trashed = Photo::factory()->for($this->household)->create(['deleted_at' => now()]);
        $gone = Photo::factory()->for($this->household)->create(['deleted_at' => now()]);
        Storage::disk('local')->put($gone->path, 'x');

        Livewire::test(ListPhotos::class)
            ->assertCanSeeTableRecords([$kept])
            ->assertCanNotSeeTableRecords([$trashed])
            ->filterTable('trashed', false)
            ->assertCanSeeTableRecords([$trashed, $gone])
            ->assertCanNotSeeTableRecords([$kept])
            ->callAction(TestAction::make('restore')->table($trashed))
            ->callAction(TestAction::make('forceDelete')->table($gone));

        $this->assertNotSoftDeleted($trashed);
        $this->assertModelMissing($gone);
        Storage::disk('local')->assertMissing($gone->path);
    }

    public function test_the_preview_of_a_trashed_photo_still_opens(): void
    {
        $photo = Photo::factory()->for($this->household)->create(['deleted_at' => now()]);
        Storage::disk('local')->put($photo->path, 'x');

        $this->get(route('photo.preview', $photo))->assertOk();
    }
}
