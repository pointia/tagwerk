<?php

declare(strict_types=1);

namespace App\Filament\Resources\Accounts\Schemas;

use App\Models\Membership;
use App\Support\HouseholdContext;
use App\Support\OutgoingMail;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('person_id')
                    ->label(__('Person'))
                    ->helperText(__('What the account may do is set on the person under “Permissions”.'))
                    // The relation leaves the scope out (sign-in reads it
                    // without a context), so the household is named here.
                    ->relationship('person', 'name', fn (Builder $query) => $query->where('household_id', app(HouseholdContext::class)->id()))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->validationMessages(['unique' => __('This person already has an account.')]),
                TextInput::make('email')
                    ->label(__('Email'))
                    ->helperText(fn ($livewire, ?Membership $record): ?string => match (true) {
                        $livewire instanceof CreateRecord && OutgoingMail::enabled() => __('Gets an invitation and sets the password themselves.'),
                        self::isShared($record) => __('This account also belongs to another household; only its owner changes the address and password, in the profile.'),
                        default => null,
                    })
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->disabled(fn (?Membership $record): bool => self::isShared($record))
                    // Only an existing account's own address is checked: on
                    // create a known address joins, and the answer must not
                    // tell whether it was known.
                    ->rules(fn ($livewire, ?Membership $record): array => $livewire instanceof CreateRecord
                        ? []
                        : [Rule::unique('users', 'email')->ignore($record?->user_id)]),
                // On create only without mail: otherwise the invitation sets it.
                TextInput::make('password')
                    ->label(__('Password'))
                    ->password()
                    ->revealable()
                    ->minLength(10)
                    ->hidden(fn ($livewire, ?Membership $record): bool => ($livewire instanceof CreateRecord && OutgoingMail::enabled()) || self::isShared($record))
                    ->required(fn ($livewire): bool => $livewire instanceof CreateRecord)
                    // Empty means: keep the current one.
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn ($livewire): string => $livewire instanceof CreateRecord
                        ? __('This server sends no mail: set the password here and pass it on. An address that already has an account keeps its password.')
                        : __('Leave empty to keep the current one. A new one signs out every phone of this account.')),
            ]);
    }

    /**
     * An account in more than one household belongs to none of them alone:
     * whoever could change its address or password would take it over in
     * the other household too.
     */
    public static function isShared(?Membership $record): bool
    {
        return $record?->user !== null && $record->user->memberships()->count() > 1;
    }
}
