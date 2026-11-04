<?php

declare(strict_types=1);

namespace App\Filament\Resources\Calendars\Tables;

use App\Models\Calendar;
use App\Models\Person;
use App\Support\HouseholdTimezone;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CalendarsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('people'))
            ->columns([
                ColorColumn::make('color')
                    ->label(__('Colour')),
                TextColumn::make('summary')
                    ->label(__('Calendar'))
                    ->searchable()
                    ->sortable(),
                // Faces instead of names: a shared calendar stays one row wide.
                ImageColumn::make('owners')
                    ->label(__('Belongs to'))
                    ->state(fn (Calendar $record): array => $record->people->map(fn (Person $person): string => $person->face())->all())
                    ->circular()
                    ->stacked()
                    ->imageSize(28)
                    ->tooltip(fn (Calendar $record): string => $record->people->isEmpty()
                        ? __('Family calendar — applies to everyone')
                        : $record->people->pluck('name')->join(', '))
                    ->placeholder(__('Everyone')),
                TextColumn::make('source.account_label')
                    ->label(__('Account'))
                    ->toggleable(),
                ToggleColumn::make('is_active')
                    ->label(__('Show'))
                    ->tooltip(__('Only calendars shown are synced and can be chosen on wall and phone.'))
                    ->afterStateUpdated(fn (Calendar $record, bool $state) => Notification::make()
                        ->title($state
                            ? __('Calendar “:name” is shown', ['name' => $record->summary])
                            : __('Calendar “:name” is hidden', ['name' => $record->summary]))
                        ->success()
                        ->send()),
                TextColumn::make('last_synced_at')
                    ->label(__('Last synced'))
                    ->timezone(fn (): string => HouseholdTimezone::current())
                    ->dateTime()
                    ->placeholder(__('never'))
                    ->sortable(),
                TextColumn::make('push_expires_at')
                    ->label(__('Channel until'))
                    ->timezone(fn (): string => HouseholdTimezone::current())
                    ->dateTime()
                    ->placeholder(__('no channel'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('summary')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
