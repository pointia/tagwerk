<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Database\Factories\RewardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Reward extends Model
{
    /** @use HasFactory<RewardFactory> */
    use BelongsToHousehold, HasFactory;

    protected $fillable = [
        'household_id', 'name', 'icon', 'description', 'cost', 'active', 'position',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['cost' => 'integer', 'active' => 'boolean'];
    }

    /** @return HasMany<RewardRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(RewardRedemption::class);
    }
}
