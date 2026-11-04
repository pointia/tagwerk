<?php

namespace App\Filament\Resources\Checklists\Tables;

use App\Enums\ListKind;
use App\Integrations\ProviderRegistry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChecklistsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('color')
                    ->label(__('Colour')),
                TextColumn::make('name')
                    ->label(__('List'))
                    ->weight(FontWeight::Medium)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('kind')
                    ->label(__('Kind'))
                    ->badge()
                    ->color('gray')
                    ->icon(fn (ListKind $state): Heroicon => match ($state) {
                        ListKind::Shopping => Heroicon::OutlinedShoppingCart,
                        ListKind::Packing => Heroicon::OutlinedBriefcase,
                        ListKind::Generic => Heroicon::OutlinedListBullet,
                    })
                    ->formatStateUsing(fn (ListKind $state): string => $state->label()),
                TextColumn::make('list_provider')
                    ->label(__('Pushes to'))
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (string $state): string => app(ProviderRegistry::class)
                        ->listProvider($state)
                        ->label()),
            ])
            ->defaultSort('position')
            ->emptyStateIcon(Heroicon::OutlinedListBullet)
            ->emptyStateHeading(__('No lists yet'))
            ->emptyStateDescription(__('Shopping and packing lists can then be ticked off on the wall and the phone.'))
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
