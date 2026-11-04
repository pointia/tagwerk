<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recipes\Tables;

use App\Models\Recipe;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecipesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->searchable()
                    ->sortable(),

                /*
                 * Via state() and not via the field name: for an array field
                 * Filament treats every entry as a value of its own and runs
                 * the formatting per ingredient — three ingredients became
                 * "3, 3, 3".
                 */
                TextColumn::make('minutes')
                    ->label(__('Minutes'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('categories')
                    ->label(__('Categories'))
                    ->badge()
                    ->toggleable(),

                TextColumn::make('ingredient_count')
                    ->label(__('Ingredients'))
                    ->state(fn (Recipe $record): int => count($record->ingredientList())),
            ])
            ->defaultSort('title')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
