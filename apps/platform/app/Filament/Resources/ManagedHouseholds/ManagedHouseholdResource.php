<?php

declare(strict_types=1);

namespace App\Filament\Resources\ManagedHouseholds;

use App\Filament\NavigationGroup;
use App\Filament\Resources\ManagedHouseholds\Pages\ListManagedHouseholds;
use App\Filament\Resources\ManagedHouseholds\Tables\ManagedHouseholdsTable;
use App\Models\Household;
use App\Models\SupportAccess;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * All households on the server, for the operator only. The content of a
 * household is reached through the support access, which leaves a trace.
 *
 * Related rows carry the household scope, which shows nothing without a
 * context. The counts therefore lift it explicitly.
 */
class ManagedHouseholdResource extends Resource
{
    protected static ?string $model = Household::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static ?string $slug = 'operator/households';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Operations;

    public static function getModelLabel(): string
    {
        return __('Household');
    }

    public static function getPluralModelLabel(): string
    {
        return __('All households');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->is_operator === true;
    }

    public static function table(Table $table): Table
    {
        return ManagedHouseholdsTable::configure($table);
    }

    /** @return Builder<Household> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->addSelect(['last_support_at' => SupportAccess::query()
                ->select('created_at')
                ->whereColumn('household_id', 'households.id')
                ->latest()
                ->limit(1)])
            ->withCount([
                'people' => fn (Builder $query) => $query->withoutGlobalScope('household'),
                'devices' => fn (Builder $query) => $query->withoutGlobalScope('household'),
                'users',
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListManagedHouseholds::route('/'),
        ];
    }
}
