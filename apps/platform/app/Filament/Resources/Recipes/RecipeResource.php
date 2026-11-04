<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recipes;

use App\Enums\Feature;
use App\Filament\Concerns\NeedsHousehold;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Recipes\Pages\CreateRecipe;
use App\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Filament\Resources\Recipes\Schemas\RecipeForm;
use App\Filament\Resources\Recipes\Tables\RecipesTable;
use App\Models\Household;
use App\Models\Recipe;
use App\Support\HouseholdContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RecipeResource extends Resource
{
    use NeedsHousehold;

    protected static ?string $model = Recipe::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::BookOpen;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Everyday;

    protected static ?int $navigationSort = 50;

    public static function getModelLabel(): string
    {
        return __('Recipe');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Recipes');
    }

    /**
     * Whoever disabled the area should not see it in the admin either —
     * otherwise someone would maintain recipes that never show on the wall.
     */
    public static function shouldRegisterNavigation(): bool
    {
        $household = Household::query()->find(app(HouseholdContext::class)->id());

        return $household === null || $household->has(Feature::Recipes);
    }

    public static function form(Schema $schema): Schema
    {
        return RecipeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecipesTable::configure($table);
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListRecipes::route('/'),
            'create' => CreateRecipe::route('/create'),
            'edit' => EditRecipe::route('/{record}/edit'),
        ];
    }
}
