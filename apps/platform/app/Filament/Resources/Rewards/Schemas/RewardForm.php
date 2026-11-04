<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Schema;

/**
 * Without `household_id`: `BelongsToHousehold` sets the column in the creating
 * hook. As a select field it would offer every household — `Household` is the
 * tenant itself and therefore carries no scope that would narrow the list.
 */
class RewardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Reward'))
                    ->required()
                    ->maxLength(255),

                // As with tasks: the symbol carries the tile, not the text.
                ViewField::make('icon')
                    ->label(__('Icon'))
                    ->view('filament.forms.icon-picker')
                    ->viewData(['placeholder' => '🍦'])
                    ->rule('nullable')
                    ->rule('string')
                    ->rule('max:16')
                    ->columnSpanFull(),

                TextInput::make('description')
                    ->label(__('Description'))
                    ->helperText(__('What exactly it includes — for example “at the weekend, after dinner”.'))
                    ->maxLength(255),

                TextInput::make('cost')
                    ->label(__('Cost in stars'))
                    ->numeric()
                    ->minValue(1)
                    ->required(),

                Toggle::make('active')
                    ->label(__('Active'))
                    ->helperText(__('Rewards switched off do not appear on the tablet; redemptions already approved are kept.'))
                    ->default(true),

                TextInput::make('position')
                    ->label(__('Order'))
                    ->numeric()
                    ->default(0),
            ]);
    }
}
