<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards;

use App\Filament\Concerns\NeedsHousehold;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Rewards\Pages\CreateReward;
use App\Filament\Resources\Rewards\Pages\EditReward;
use App\Filament\Resources\Rewards\Pages\ListRewards;
use App\Filament\Resources\Rewards\Schemas\RewardForm;
use App\Filament\Resources\Rewards\Tables\RewardsTable;
use App\Models\Reward;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RewardResource extends Resource
{
    use NeedsHousehold;

    protected static ?string $model = Reward::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Gift;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Everyday;

    protected static ?int $navigationSort = 30;

    public static function getModelLabel(): string
    {
        return __('Reward');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Rewards');
    }

    public static function form(Schema $schema): Schema
    {
        return RewardForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RewardsTable::configure($table);
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
            'index' => ListRewards::route('/'),
            'create' => CreateReward::route('/create'),
            'edit' => EditReward::route('/{record}/edit'),
        ];
    }
}
