<?php

namespace App\Filament\Resources\Households\Pages;

use App\Filament\Resources\Households\HouseholdResource;
use Filament\Resources\Pages\EditRecord;

class EditHousehold extends EditRecord
{
    protected static string $resource = HouseholdResource::class;

    // No deleting: all of the family's data would vanish with the household.
}
