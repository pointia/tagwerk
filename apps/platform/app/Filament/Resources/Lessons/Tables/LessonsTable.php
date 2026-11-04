<?php

declare(strict_types=1);

namespace App\Filament\Resources\Lessons\Tables;

use App\Enums\LessonCadence;
use App\Filament\Resources\Lessons\Schemas\LessonForm;
use App\Models\Lesson;
use App\Models\Person;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class LessonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultGroup('weekday')
            ->groups([
                Group::make('weekday')
                    ->label(__('Weekday'))
                    ->getTitleFromRecordUsing(
                        fn (Lesson $record): string => LessonForm::weekdays()[$record->weekday] ?? '—',
                    ),
            ])
            ->defaultSort('period_number')
            ->columns([
                TextColumn::make('person.name')
                    ->label(__('Child'))
                    ->sortable()
                    ->searchable(),

                ColorColumn::make('color')
                    ->label(__('Colour')),

                TextColumn::make('title')
                    ->label(__('Subject'))
                    ->searchable(),

                /*
                 * Via state() and a column name of its own: depending on the
                 * lesson the time is in two different fields, and the
                 * formatting should only run once.
                 */
                TextColumn::make('time')
                    ->label(__('Slot'))
                    ->state(fn (Lesson $record): string => $record->usesPeriod()
                        ? __('Period :number', ['number' => $record->period_number])
                        : substr((string) $record->starts_at, 0, 5).'–'.substr((string) $record->ends_at, 0, 5)),

                TextColumn::make('frequency')
                    ->label(__('How often'))
                    ->state(fn (Lesson $record): string => $record->interval <= 1
                        ? __('every week')
                        : __('every other week, :cadence', ['cadence' => $record->cadence->label()]))
                    ->color(fn (Lesson $record): string => $record->interval <= 1 ? 'gray' : 'warning'),

                IconColumn::make('active')
                    ->label(__('Active'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('person_id')
                    ->label(__('Child'))
                    ->options(fn (): array => Person::query()->children()->ordered()->pluck('name', 'id')->all()),

                SelectFilter::make('cadence')
                    ->label(__('Rhythm'))
                    ->options(collect(LessonCadence::cases())
                        ->mapWithKeys(fn (LessonCadence $cadence): array => [$cadence->value => $cadence->label()])
                        ->all()),
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
            ->emptyStateHeading(__('No timetable yet'))
            ->emptyStateDescription(__('Enter a child\'s lessons. Holidays and public holidays cancel them by themselves.'));
    }
}
