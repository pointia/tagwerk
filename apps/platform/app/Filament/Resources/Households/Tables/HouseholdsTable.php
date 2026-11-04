<?php

namespace App\Filament\Resources\Households\Tables;

use App\Support\HouseholdTimezone;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HouseholdsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Household'))
                    ->weight(FontWeight::Medium)
                    ->searchable(),
                TextColumn::make('address')
                    ->label(__('Place'))
                    ->placeholder('—'),
                TextColumn::make('holiday_region')
                    ->label(__('Holidays'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->label(__('Created on'))
                    ->timezone(fn (): string => HouseholdTimezone::current())
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
