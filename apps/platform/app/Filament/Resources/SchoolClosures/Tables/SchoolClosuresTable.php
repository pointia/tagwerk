<?php

declare(strict_types=1);

namespace App\Filament\Resources\SchoolClosures\Tables;

use App\Models\SchoolClosure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SchoolClosuresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on')
            ->columns([
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->searchable(),

                TextColumn::make('starts_on')
                    ->label(__('From'))
                    ->date()
                    ->sortable(),

                TextColumn::make('ends_on')
                    ->label(__('To'))
                    ->date()
                    ->sortable(),

                TextColumn::make('duration')
                    ->label(__('Days'))
                    ->state(fn (SchoolClosure $record): int => (int) $record->starts_on->diffInDays($record->ends_on) + 1),

                TextColumn::make('person.name')
                    ->label(__('Applies to'))
                    ->placeholder(__('All children')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('Nothing entered yet'))
            ->emptyStateDescription(__('Public holidays come by themselves — school holidays and days off set by the school go here.'));
    }
}
