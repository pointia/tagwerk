<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasZonedTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * An account in a household, as one of its people.
 *
 * Deliberately without `BelongsToHousehold`: signing in and switching read
 * across all households of an account before any context exists. A
 * membership is therefore always found from its account
 * (`$user->memberships()`) or for a household known to the request — never
 * by a free query.
 */
final class Membership extends Model
{
    use HasZonedTimestamps;

    protected $fillable = ['user_id', 'household_id', 'person_id', 'preferences', 'accepted_at', 'dismissed_notices'];

    /**
     * The operator's account is for running the server and support: looking
     * into a household always goes through the logged support access, never
     * through a membership.
     */
    protected static function booted(): void
    {
        self::creating(function (self $membership): void {
            if (User::query()->whereKey($membership->user_id)->value('is_operator') === true) {
                throw new LogicException('An operator account cannot join a household.');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'preferences' => 'array',
            'dismissed_notices' => 'array',
            'accepted_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class)->withoutGlobalScope('household');
    }

    /** @param  Builder<self>  $query */
    public function scopeAccepted(Builder $query): void
    {
        $query->whereNotNull('accepted_at');
    }
}
