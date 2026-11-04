<?php

declare(strict_types=1);

namespace App\Filament\Resources\PointEntries;

use App\Filament\Concerns\NeedsHousehold;
use App\Filament\NavigationGroup;
use App\Filament\Resources\PointEntries\Pages\ListPointEntries;
use App\Filament\Resources\PointEntries\Schemas\PointEntryForm;
use App\Filament\Resources\PointEntries\Tables\PointEntriesTable;
use App\Models\PointEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The stars ledger, for looking back and putting right.
 *
 * Only a list: new stars come from ticking off, rewards and corrections at
 * the wall. Changing and deleting happen in place, in a modal.
 */
class PointEntryResource extends Resource
{
    use NeedsHousehold;

    protected static ?string $model = PointEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Star;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Everyday;

    protected static ?int $navigationSort = 31;

    public static function getNavigationLabel(): string
    {
        return __('Star history');
    }

    public static function getModelLabel(): string
    {
        return __('Entry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Star history');
    }

    public static function form(Schema $schema): Schema
    {
        return PointEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PointEntriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPointEntries::route('/'),
        ];
    }
}
