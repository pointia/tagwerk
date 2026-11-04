<?php

declare(strict_types=1);

namespace App\Filament\Resources\PointEntries\Tables;

use App\Models\PointEntry;
use App\Support\HouseholdTimezone;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PointEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['person', 'createdBy']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('When'))
                    ->dateTime()
                    // As the family reads the clock, not in the server's UTC.
                    ->timezone(fn (): string => HouseholdTimezone::current())
                    ->sortable(),
                TextColumn::make('person.name')
                    ->label(__('Person'))
                    ->sortable(),
                TextColumn::make('points')
                    ->label(__('Stars'))
                    ->numeric()
                    ->alignEnd()
                    ->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').$state)
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('reason')
                    ->label(__('Reason'))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('source')
                    ->label(__('Source'))
                    ->state(fn (PointEntry $record): string => match (true) {
                        $record->gift_id !== null => __('Gift'),
                        $record->task_completion_id !== null => __('Task'),
                        $record->reward_redemption_id !== null => __('Reward'),
                        $record->created_by_user_id !== null => 'Von '.$record->createdBy?->name,
                        default => __('Correction'),
                    })
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('person_id')
                    ->label(__('Person'))
                    ->relationship('person', 'name', fn (Builder $query): Builder => $query->ordered()),
                TernaryFilter::make('direction')
                    ->label(__('Direction'))
                    ->placeholder(__('All entries'))
                    ->trueLabel(__('Credits only'))
                    ->falseLabel(__('Deductions only'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('points', '>', 0),
                        false: fn (Builder $query): Builder => $query->where('points', '<', 0),
                    ),
            ])
            ->recordActions([
                // Changing one half would leave a gift that does not add up; deleting takes both.
                EditAction::make()->modalWidth('md')
                    ->hidden(fn (PointEntry $record): bool => $record->gift_id !== null),
                DeleteAction::make()
                    ->modalDescription(__('The balance goes down or up by exactly these stars. The task itself stays ticked.')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedStar)
            ->emptyStateHeading(__('No stars booked yet'))
            ->emptyStateDescription(__('Stars come from ticking off tasks, redeeming rewards and corrections on the wall.'));
    }
}
