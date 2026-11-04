<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards\Tables;

use App\Models\Reward;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RewardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Reward'))
                    ->formatStateUsing(fn (Reward $record): string => trim($record->icon.' '.$record->name))
                    ->searchable(),
                TextColumn::make('description')
                    ->label(__('Description'))
                    ->toggleable(),
                TextColumn::make('cost')
                    ->label(__('Stars'))
                    ->numeric()
                    ->sortable(),
                IconColumn::make('active')
                    ->label(__('Active'))
                    ->boolean(),
                TextColumn::make('position')
                    ->label(__('Order'))
                    ->numeric()
                    ->sortable(),
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
