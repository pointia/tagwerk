<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use App\Models\Concerns\HasZonedTimestamps;
use Database\Factories\ListItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ListItem extends Model
{
    /** @use HasFactory<ListItemFactory> */
    use BelongsToHousehold, HasFactory, HasZonedTimestamps;

    protected $fillable = [
        'household_id', 'list_id', 'title', 'quantity', 'done_at', 'position',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['done_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Checklist, $this> */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class, 'list_id');
    }

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }
}
