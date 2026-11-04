<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ListKind;
use App\Models\Concerns\BelongsToHousehold;
use Database\Factories\ChecklistFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A list: shopping, packing or free-form.
 *
 * Called `Checklist` and not `List` because `list` is a language construct in
 * PHP — `class List` would be a parse error. The table is called `lists` as
 * in the design.
 */
final class Checklist extends Model
{
    /** @use HasFactory<ChecklistFactory> */
    use BelongsToHousehold, HasFactory;

    protected $table = 'lists';

    protected $fillable = [
        'household_id', 'name', 'kind', 'color',
        'list_provider', 'list_account_id', 'list_target', 'position',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => ListKind::class,
        ];
    }

    /** @return BelongsTo<ProviderAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ProviderAccount::class, 'list_account_id');
    }

    /** @return HasMany<ListItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ListItem::class, 'list_id');
    }

    /** The list at the provider; without an explicit choice the one with this name. */
    public function remoteTarget(): string
    {
        return $this->list_target ?? $this->name;
    }
}
