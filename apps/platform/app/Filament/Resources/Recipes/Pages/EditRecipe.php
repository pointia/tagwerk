<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recipes\Pages;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Support\Recipes\RecipeInput;
use Filament\Resources\Pages\EditRecord;

class EditRecipe extends EditRecord
{
    protected static string $resource = RecipeResource::class;

    /**
     * Amounts arrive from the repeater as text; stored as numbers, like the
     * device's and the importers' recipes.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return [...$data, ...RecipeInput::attributes(array_intersect_key($data, ['ingredients' => true, 'categories' => true]))];
    }
}
