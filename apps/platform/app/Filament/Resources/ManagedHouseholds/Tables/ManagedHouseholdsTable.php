<?php

declare(strict_types=1);

namespace App\Filament\Resources\ManagedHouseholds\Tables;

use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdDeletion;
use App\Services\SupportAccessService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ManagedHouseholdsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label(__('Accounts'))
                    ->numeric(),
                TextColumn::make('people_count')
                    ->label(__('People'))
                    ->numeric(),
                TextColumn::make('devices_count')
                    ->label(__('Devices'))
                    ->numeric(),
                IconColumn::make('suspended_at')
                    ->label(__('Active'))
                    ->state(fn (Household $record): bool => $record->suspended_at === null)
                    ->boolean(),
                TextColumn::make('read_only_since')
                    ->label(__('Read-only since'))
                    ->date()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->date()
                    ->sortable(),
                TextColumn::make('last_support_at')
                    ->label(__('Last support'))
                    ->dateTime()
                    // Each row in its own household's zone: the operator has none.
                    ->timezone(fn (Household $record): string => $record->timezone ?? (string) config('app.timezone'))
                    ->placeholder(__('never')),
            ])
            ->defaultSort('created_at')
            ->recordActions([
                Action::make('support')
                    ->label(__('Open support access'))
                    ->icon('heroicon-o-lifebuoy')
                    ->modalDescription(__('You then see and change the household like a parent. The access is logged, and the family sees it in their settings.'))
                    ->requiresConfirmation()
                    ->action(function (Household $record, SupportAccessService $support) {
                        $user = auth()->user();
                        abort_unless($user instanceof User, 403);
                        $support->enter($user, $record);

                        return redirect(Dashboard::getUrl());
                    }),
                Action::make('suspend')
                    ->label(__('Suspend'))
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('Wall, phones and admin panel of this household lose access, syncs pause. The data is kept.'))
                    ->visible(fn (Household $record): bool => $record->suspended_at === null)
                    ->action(fn (Household $record) => $record->forceFill(['suspended_at' => now()])->save()),
                Action::make('resume')
                    ->label(__('Unsuspend'))
                    ->icon('heroicon-o-lock-open')
                    ->visible(fn (Household $record): bool => $record->suspended_at !== null)
                    ->action(fn (Household $record) => $record->forceFill(['suspended_at' => null])->save()),
                Action::make('read-only')
                    ->label(__('Make read-only'))
                    ->icon('heroicon-o-eye')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(__('Wall, phones and admin panel still show everything, but nothing can be changed, and syncs pause. The data is kept.'))
                    ->visible(fn (Household $record): bool => ! $record->isReadOnly())
                    ->action(fn (Household $record) => $record->forceFill(['read_only_since' => now()])->save()),
                Action::make('writable')
                    ->label(__('Make writable'))
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (Household $record): bool => $record->isReadOnly())
                    ->action(fn (Household $record) => $record->forceFill(['read_only_since' => null])->save()),
                // Typing the name is the second step: the button sits next to harmless ones.
                Action::make('delete')
                    ->label(__('Delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Household $record): string => __('Delete :name?', ['name' => $record->name]))
                    ->modalDescription(__('Everything goes: calendars, tasks, stars, photos, recipes, and every account that belongs to no other household. This cannot be undone.'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Type the household name to confirm'))
                            ->required()
                            ->in(fn (Household $record): array => [$record->name])
                            ->validationMessages(['in' => __('The name does not match.')]),
                    ])
                    ->action(function (Household $record, HouseholdDeletion $deletion): void {
                        $deletion->delete($record);

                        Notification::make()->success()->title(__('Household deleted.'))->send();
                    }),
            ]);
    }
}
