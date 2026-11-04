<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Database\Factories\PointEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An entry in the points ledger.
 *
 * The wall never changes an entry: correcting a credit there means booking
 * against it, so the child can follow what happened when. Only parents in
 * the admin change or delete one — for a double tap or a typo.
 */
final class PointEntry extends Model
{
    /** @use HasFactory<PointEntryFactory> */
    use BelongsToHousehold, HasFactory;

    protected $fillable = [
        'household_id', 'person_id', 'points', 'reason',
        'task_completion_id', 'reward_redemption_id', 'created_by_user_id', 'bonus_on', 'gift_id',
    ];

    protected static function booted(): void
    {
        // Half a gift would be stars from nowhere or into nowhere.
        self::deleted(function (PointEntry $entry): void {
            if ($entry->gift_id !== null) {
                self::query()->where('gift_id', $entry->gift_id)->delete();
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['points' => 'integer', 'bonus_on' => 'immutable_date'];
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** The balance is the sum, not a maintained field. */
    public static function balanceFor(int $personId): int
    {
        return (int) self::query()->where('person_id', $personId)->sum('points');
    }
}
