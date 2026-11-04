<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use App\Models\Concerns\HasRecipeContent;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A recipe in the household's own book — written by the family, or copied
 * in from a link or a collection (`source`, `source_url`, `license`).
 *
 * Ingredients are `{amount, unit, name, note, group}`; see `Ingredient`.
 */
final class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use BelongsToHousehold, HasFactory, HasRecipeContent;

    public const PHOTO_DIRECTORY = 'recipes';

    /** A card has a front and a back, a cookbook recipe a page or two. */
    public const MAX_PAGES = 8;

    protected $fillable = [
        'household_id', 'title', 'intro', 'steps', 'ingredients', 'servings', 'minutes', 'difficulty',
        'categories', 'vegetarian', 'photo_path', 'pages', 'source', 'source_id', 'source_url', 'license',
    ];

    protected static function booted(): void
    {
        // A replaced or deleted photo goes with the row; nobody else uses it.
        self::updated(static function (Recipe $recipe): void {
            $old = $recipe->getOriginal('photo_path');

            if ($recipe->wasChanged('photo_path') && is_string($old) && $old !== '') {
                Storage::disk('local')->delete($old);
            }

            if ($recipe->wasChanged('pages')) {
                $before = json_decode((string) $recipe->getRawOriginal('pages'), true);

                Storage::disk('local')->delete(array_values(array_diff(
                    is_array($before) ? $before : [],
                    $recipe->pagePaths(),
                )));
            }
        });

        self::deleted(static function (Recipe $recipe): void {
            if (is_string($recipe->photo_path) && $recipe->photo_path !== '') {
                Storage::disk('local')->delete($recipe->photo_path);
            }

            Storage::disk('local')->delete($recipe->pagePaths());
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [...$this->recipeCasts(), 'pages' => 'array'];
    }

    /** @return list<string> */
    public function pagePaths(): array
    {
        return array_values(array_filter((array) ($this->pages ?? []), static fn (mixed $path): bool => is_string($path) && $path !== ''));
    }

    /** The file name is the page's id: stable while others are added or removed. */
    public static function pageId(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /** @return list<array{id: string, url: string}> */
    public function pageUrls(): array
    {
        return array_map(fn (string $path): array => [
            'id' => self::pageId($path),
            'url' => '/api/wall/recipe-book/builtin/'.$this->getKey().'/pages/'.self::pageId($path),
        ], $this->pagePaths());
    }

    /**
     * Served through the API against the device or account token; the version
     * keeps a replaced photo from coming out of the service worker's store.
     *
     * @return array{small: string, large: string}|null
     */
    public function photoUrls(): ?array
    {
        if ($this->photo_path === null || $this->photo_path === '') {
            return null;
        }

        $url = '/api/wall/recipe-book/builtin/'.$this->getKey().'/photo?v='.substr(md5((string) $this->photo_path), 0, 8);

        return ['small' => $url, 'large' => $url];
    }
}
