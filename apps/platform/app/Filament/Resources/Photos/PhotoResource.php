<?php

declare(strict_types=1);

namespace App\Filament\Resources\Photos;

use App\Enums\Feature;
use App\Filament\Concerns\NeedsHousehold;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Photos\Pages\CreatePhoto;
use App\Filament\Resources\Photos\Pages\EditPhoto;
use App\Filament\Resources\Photos\Pages\ListPhotos;
use App\Filament\Resources\Photos\Schemas\PhotoForm;
use App\Filament\Resources\Photos\Tables\PhotosTable;
use App\Models\Household;
use App\Models\Photo;
use App\Support\HouseholdContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class PhotoResource extends Resource
{
    use NeedsHousehold;

    protected static ?string $model = Photo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Photo;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Everyday;

    protected static ?int $navigationSort = 60;

    public static function getModelLabel(): string
    {
        return __('Photo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Photos');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $household = Household::query()->find(app(HouseholdContext::class)->id());

        return $household === null || $household->has(Feature::Photos);
    }

    public static function form(Schema $schema): Schema
    {
        return PhotoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PhotosTable::configure($table);
    }

    /**
     * Trashed photos stay reachable for restore and final delete (the
     * table's trash filter); only the soft-delete scope is lifted, never
     * the household.
     *
     * @return Builder<Photo>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListPhotos::route('/'),
            'create' => CreatePhoto::route('/create'),
            'edit' => EditPhoto::route('/{record}/edit'),
        ];
    }
}
