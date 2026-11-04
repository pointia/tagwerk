<?php

declare(strict_types=1);

namespace App\Filament\Resources\Calendars\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CalendarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('summary')
                    ->label(__('Title'))
                    ->helperText(__('Comes from Google and is overwritten at the next sync.'))
                    ->disabled(),

                ColorPicker::make('color')
                    ->label(__('Colour'))
                    ->helperText(__('How the events appear on wall and phone. Stays after the sync, too.'))
                    ->required(),

                Select::make('people')
                    ->label(__('Belongs to'))
                    ->relationship('people', 'name')
                    ->multiple()
                    ->placeholder(__('Family calendar — applies to everyone'))
                    ->helperText(__('Events without their own assignment then count as events of these people.'))
                    ->searchable()
                    ->preload(),

                Toggle::make('is_active')
                    ->label(__('Show'))
                    ->helperText(__('Only calendars shown are synced and can be chosen on wall and phone. Which of them someone sees is set by each account in the app and for each tablet under Devices.')),
            ]);
    }
}
