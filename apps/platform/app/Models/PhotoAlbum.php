<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A named selection of uploaded photos, shared by the household. */
final class PhotoAlbum extends Model
{
    use BelongsToHousehold;

    protected $fillable = ['household_id', 'name'];

    /** @return BelongsToMany<Photo, $this> */
    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class, 'photo_album_photo')->withPivot('created_at');
    }
}
