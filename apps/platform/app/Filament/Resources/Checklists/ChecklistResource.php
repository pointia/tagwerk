<?php

namespace App\Filament\Resources\Checklists;

use App\Filament\Concerns\NeedsHousehold;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Checklists\Pages\CreateChecklist;
use App\Filament\Resources\Checklists\Pages\EditChecklist;
use App\Filament\Resources\Checklists\Pages\ListChecklists;
use App\Filament\Resources\Checklists\Schemas\ChecklistForm;
use App\Filament\Resources\Checklists\Tables\ChecklistsTable;
use App\Models\Checklist;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ChecklistResource extends Resource
{
    use NeedsHousehold;

    protected static ?string $model = Checklist::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ListBullet;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Everyday;

    protected static ?int $navigationSort = 40;

    public static function getModelLabel(): string
    {
        return __('List');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Lists');
    }

    public static function form(Schema $schema): Schema
    {
        return ChecklistForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChecklistsTable::configure($table);
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
            'index' => ListChecklists::route('/'),
            'create' => CreateChecklist::route('/create'),
            'edit' => EditChecklist::route('/{record}/edit'),
        ];
    }
}
