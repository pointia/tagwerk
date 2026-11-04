<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MealKind;
use App\Models\Concerns\BelongsToHousehold;
use App\Models\Concerns\HasZonedTimestamps;
use Database\Factories\MealPlanEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class MealPlanEntry extends Model
{
    /** @use HasFactory<MealPlanEntryFactory> */
    use BelongsToHousehold, HasFactory, HasZonedTimestamps;

    protected $fillable = [
        'household_id', 'planned_on', 'kind',
        'recipe_id', 'recipe_provider', 'title', 'notes', 'position', 'eaters',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'planned_on' => 'immutable_date',
            'kind' => MealKind::class,
            'eaters' => 'array',
        ];
    }

    public function hasRecipe(): bool
    {
        return $this->recipe_id !== null;
    }
}
