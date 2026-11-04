<?php

declare(strict_types=1);

namespace App\Filament\Resources\ManagedHouseholds\Pages;

use App\Filament\Resources\ManagedHouseholds\ManagedHouseholdResource;
use App\Services\HouseholdOnboarding;
use App\Support\Locale;
use App\Support\OutgoingMail;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListManagedHouseholds extends ListRecords
{
    protected static string $resource = ManagedHouseholdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Not a create page: a household without its first parent would
            // be a household nobody can ever enter.
            Action::make('create')
                ->label(__('Create household'))
                ->icon('heroicon-o-plus')
                ->schema([
                    TextInput::make('household')
                        ->label(__('Household name'))
                        ->placeholder(__('The Smiths'))
                        ->required()
                        ->maxLength(255),
                    Select::make('timezone')
                        ->label(__('Time zone'))
                        ->helperText(__('Follows the place later, once the household enters it.'))
                        ->options(fn (): array => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                        ->default('Europe/Vienna')
                        ->searchable()
                        ->required(),
                    Select::make('locale')
                        ->label(__('Language'))
                        ->helperText(__('For the wall and every account that has not chosen its own.'))
                        ->options(Locale::supported())
                        ->default(config('app.locale'))
                        ->selectablePlaceholder(false)
                        ->required(),
                    TextInput::make('parent')
                        ->label(__('First parent'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label(__('Email'))
                        ->helperText(OutgoingMail::enabled() ? __('Gets an invitation and sets the password themselves.') : null)
                        ->email()
                        ->required()
                        ->unique('users', 'email'),
                    TextInput::make('password')
                        ->label(__('Password'))
                        ->password()
                        ->revealable()
                        ->minLength(10)
                        ->required()
                        ->visible(fn (): bool => ! OutgoingMail::enabled())
                        ->helperText(__('This server sends no mail: set the password here and pass it on.')),
                ])
                ->action(function (array $data, HouseholdOnboarding $onboarding): void {
                    $onboarding->create(
                        $data['household'],
                        $data['timezone'],
                        $data['parent'],
                        $data['email'],
                        password: $data['password'] ?? null,
                        locale: $data['locale'],
                    );
                })
                ->successNotificationTitle(OutgoingMail::enabled() ? __('Household created, invitation sent') : __('Household created')),
        ];
    }
}
