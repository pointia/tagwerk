<?php

declare(strict_types=1);

namespace App\Filament\Resources\Photos\Tables;

use App\Models\Photo;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PhotosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                /*
                 * The preview through our own route: the file is on the
                 * private disk and so has no public URL Filament could build
                 * by itself.
                 */
                ImageColumn::make('path')
                    ->label(__('Picture'))
                    ->state(fn (Photo $record): string => route('photo.preview', $record))
                    ->height(64),

                TextColumn::make('caption')
                    ->label(__('Caption'))
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('taken_on')
                    ->label(__('Taken'))
                    ->date()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            // Deleting moves to the trash, as on the phone; the nightly run empties it.
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ]);
    }
}
