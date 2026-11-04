<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use App\Services\PhotoImage;
use Database\Factories\PhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * A photo uploaded by the household.
 *
 * The file lives on private storage. Whoever wants to see it asks the API —
 * so a family photo stays bound to a device that belongs to the household.
 */
final class Photo extends Model
{
    /** @use HasFactory<PhotoFactory> */
    use BelongsToHousehold, HasFactory, SoftDeletes;

    /** How long a deleted photo waits in the trash before it is gone for good. */
    public const TRASH_DAYS = 30;

    protected $fillable = ['household_id', 'path', 'caption', 'taken_on', 'fingerprint', 'user_id', 'favorite'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'taken_on' => 'immutable_date',
            'favorite' => 'boolean',
        ];
    }

    /** @return BelongsToMany<PhotoAlbum, $this> */
    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(PhotoAlbum::class, 'photo_album_photo');
    }

    /** Only the final delete takes the files along; the trash keeps them. */
    protected static function booted(): void
    {
        self::forceDeleted(function (Photo $photo): void {
            Storage::disk('local')->delete([$photo->path, PhotoImage::thumbnailPath($photo->path)]);
        });
    }

    /** Whole days left in the trash; null while not deleted. */
    public function daysLeft(): ?int
    {
        if ($this->deleted_at === null) {
            return null;
        }

        return max(0, self::TRASH_DAYS - (int) $this->deleted_at->diffInDays(now()));
    }
}
