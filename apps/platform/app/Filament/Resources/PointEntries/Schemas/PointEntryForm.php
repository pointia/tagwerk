<?php

declare(strict_types=1);

namespace App\Filament\Resources\PointEntries\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PointEntryForm
{
    /*
     * Who and where from stay as booked: a star for the wrong child is
     * deleted and booked again at the wall, so the ledger never claims a
     * completion for someone who did not do it.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('points')
                    ->label(__('Stars'))
                    ->helperText(__('Negative for deductions and redeemed rewards.'))
                    ->integer()
                    ->required()
                    ->notIn([0])
                    ->minValue(-10000)
                    ->maxValue(10000),
                TextInput::make('reason')
                    ->label(__('Reason'))
                    ->required()
                    ->maxLength(255),
            ]);
    }
}
