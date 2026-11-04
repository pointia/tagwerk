<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;

/**
 * A recipe the household likes — from any source, addressed like a planned
 * meal: provider and recipe id.
 */
final class RecipeFavorite extends Model
{
    use BelongsToHousehold;

    protected $fillable = ['household_id', 'provider', 'recipe_id', 'title'];
}
