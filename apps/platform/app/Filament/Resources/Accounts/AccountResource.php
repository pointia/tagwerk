<?php

declare(strict_types=1);

namespace App\Filament\Resources\Accounts;

use App\Filament\Concerns\NeedsHousehold;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Resources\Accounts\Pages\EditAccount;
use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\Accounts\Schemas\AccountForm;
use App\Filament\Resources\Accounts\Tables\AccountsTable;
use App\Models\Membership;
use App\Support\HouseholdContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The logins of the household — for the phone, and for adults the admin panel.
 *
 * A row is a membership: one account may belong to several households, and
 * each lists only its own. `Membership` carries no household scope (signing
 * in reads it before any context exists), so the restriction lives here.
 */
class AccountResource extends Resource
{
    use NeedsHousehold;

    protected static ?string $model = Membership::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Key;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Family;

    protected static ?int $navigationSort = 11;

    public static function getModelLabel(): string
    {
        return __('Account');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Accounts');
    }

    public static function form(Schema $schema): Schema
    {
        return AccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountsTable::configure($table);
    }

    /** @return Builder<Membership> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('household_id', app(HouseholdContext::class)->id())
            ->with(['user', 'person']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccounts::route('/'),
            'create' => CreateAccount::route('/create'),
            'edit' => EditAccount::route('/{record}/edit'),
        ];
    }
}
