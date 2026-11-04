<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use App\Models\Concerns\HasZonedTimestamps;
use Database\Factories\RewardRedemptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RewardRedemption extends Model
{
    /** @use HasFactory<RewardRedemptionFactory> */
    use BelongsToHousehold, HasFactory, HasZonedTimestamps;

    protected $fillable = [
        'household_id', 'reward_id', 'person_id', 'cost',
        'approved_at', 'rejected_at', 'approved_by_user_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cost' => 'integer',
            'approved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Reward, $this> */
    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function isPending(): bool
    {
        return $this->approved_at === null && $this->rejected_at === null;
    }
}
