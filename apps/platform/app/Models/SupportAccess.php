<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasZonedTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The operator entered a household. Without the household scope: the entry
 * belongs to the operator's work, and the household reads it by its id.
 */
final class SupportAccess extends Model
{
    use HasZonedTimestamps;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'household_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
