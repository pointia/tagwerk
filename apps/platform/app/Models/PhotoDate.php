<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;

/** When a picture in a folder was taken, read once from the file. */
final class PhotoDate extends Model
{
    use BelongsToHousehold;

    protected $fillable = ['household_id', 'provider_account_id', 'path', 'taken_on'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'taken_on' => 'immutable_date',
        ];
    }
}
