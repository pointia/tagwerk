<?php

declare(strict_types=1);

namespace App\Filament\Resources\SchoolClosures\Schemas;

use App\Models\Person;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/** Without `household_id` — same reasoning as in `TaskForm`. */
class SchoolClosureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->label(__('Title'))
                    ->required()
                    ->maxLength(255)
                    ->placeholder(__('Half-term, school day off'))
                    ->columnSpanFull(),

                DatePicker::make('starts_on')
                    ->label(__('From'))
                    ->required(),

                DatePicker::make('ends_on')
                    ->label(__('To'))
                    ->required()
                    ->afterOrEqual('starts_on')
                    ->helperText(__('Inclusive — the last day of the holidays is still off.')),

                Select::make('person_id')
                    ->label(__('Applies to'))
                    ->options(fn (): array => Person::query()->children()->ordered()->pluck('name', 'id')->all())
                    ->placeholder(__('All children'))
                    ->columnSpanFull()
                    ->helperText(fn (Get $get): string => $get('person_id') === null
                        ? __('Without a choice the period applies to everyone.')
                        : __('Only for this child — useful when siblings go to different schools.')),
            ]);
    }
}
