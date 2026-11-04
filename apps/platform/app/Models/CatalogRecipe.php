<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasRecipeContent;
use Illuminate\Database\Eloquent\Model;

/**
 * A recipe from a public collection, mirrored once for the whole server.
 *
 * Deliberately without `household_id`: the same 11,000 recipes per household
 * would be the same text a hundred times. Which households see them decides
 * the provider — the one that mirrors them (`source`) — through its account.
 */
final class CatalogRecipe extends Model
{
    use HasRecipeContent;

    protected $fillable = [
        'source', 'source_id', 'revision', 'title', 'intro', 'steps', 'ingredients', 'servings', 'minutes',
        'difficulty', 'categories', 'vegetarian', 'photo', 'source_url', 'license',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [...$this->recipeCasts(), 'photo' => 'array'];
    }

    /** @return array{small: string, large: string}|null */
    public function photoUrls(): ?array
    {
        $photo = $this->photo;

        return is_array($photo) && is_string($photo['small'] ?? null) && is_string($photo['large'] ?? null)
            ? ['small' => $photo['small'], 'large' => $photo['large']]
            : null;
    }

    /** @return list<array{id: string, url: string}> */
    public function pageUrls(): array
    {
        return [];
    }
}
