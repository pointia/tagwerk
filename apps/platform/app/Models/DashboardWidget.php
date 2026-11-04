<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Database\Factories\DashboardWidgetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A tile on the dashboard.
 *
 * Its position is stored in grid cells, the widget's specifics in
 * `settings` — for example which list a list tile shows. Every wall tablet
 * and every account has a dashboard of its own (`device_id` or `user_id`).
 */
final class DashboardWidget extends Model
{
    /** @use HasFactory<DashboardWidgetFactory> */
    use BelongsToHousehold, HasFactory;

    protected $fillable = ['household_id', 'device_id', 'user_id', 'type', 'x', 'y', 'w', 'h', 'settings', 'position'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'x' => 'integer',
            'y' => 'integer',
            'w' => 'integer',
            'h' => 'integer',
            'position' => 'integer',
            'settings' => 'array',
        ];
    }

    /**
     * The tiles of whoever asks: a wall tablet or an account.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOwnedBy(Builder $query, Device|User $owner): void
    {
        $query->where($owner instanceof Device ? 'device_id' : 'user_id', $owner->getKey());
    }

    /** Does this tile overlap the given area? */
    public function overlaps(int $x, int $y, int $w, int $h): bool
    {
        return $x < $this->x + $this->w
            && $x + $w > $this->x
            && $y < $this->y + $this->h
            && $y + $h > $this->y;
    }
}
