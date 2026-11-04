<?php

namespace App\Filament\Resources\Households;

use App\Filament\Concerns\NeedsHousehold;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Households\Pages\CreateHousehold;
use App\Filament\Resources\Households\Pages\EditHousehold;
use App\Filament\Resources\Households\Pages\ListHouseholds;
use App\Filament\Resources\Households\Schemas\HouseholdForm;
use App\Filament\Resources\Households\Tables\HouseholdsTable;
use App\Models\Household;
use App\Support\HouseholdContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The settings of one's own household.
 *
 * `Household` is the tenant itself and therefore carries no scope — without
 * the restriction here every family would see and edit all the others.
 */
class HouseholdResource extends Resource
{
    use NeedsHousehold;

    protected static ?string $model = Household::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::HomeModern;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Setup;

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('Household');
    }

    public static function getModelLabel(): string
    {
        return __('Household');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Households');
    }

    public static function form(Schema $schema): Schema
    {
        return HouseholdForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HouseholdsTable::configure($table);
    }

    /** @return Builder<Household> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereKey(app(HouseholdContext::class)->id());
    }

    /** There is exactly one household — the navigation leads straight into it. */
    public static function getNavigationUrl(): string
    {
        return static::getUrl('edit', ['record' => app(HouseholdContext::class)->id()]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHouseholds::route('/'),
            'create' => CreateHousehold::route('/create'),
            'edit' => EditHousehold::route('/{record}/edit'),
        ];
    }
}
