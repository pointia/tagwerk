<?php

declare(strict_types=1);

namespace App\Filament\Resources\People\Tables;

use App\Enums\PersonRole;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PeopleTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_path')
                    ->label(__('Picture'))
                    ->disk('public')
                    ->circular(),
                ColorColumn::make('color')
                    ->label(__('Colour')),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->label(__('Role'))
                    ->badge()
                    ->formatStateUsing(fn (PersonRole $state): string => $state->label()),
                TextColumn::make('birthday')
                    ->label(__('Birthday'))
                    ->date()
                    ->placeholder('—')
                    ->sortable(),
            ])
            // The family's order everywhere on the wall. Children follow
            // their age; dragging only decides between the adults.
            ->defaultSort(fn (Builder $query): Builder => $query->ordered())
            ->reorderable('position')
            ->description(__('Children are sorted by age, the oldest first. You set the order of the adults with “Reorder”.'))
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
