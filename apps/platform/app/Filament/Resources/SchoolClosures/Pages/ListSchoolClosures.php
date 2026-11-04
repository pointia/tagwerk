<?php

declare(strict_types=1);

namespace App\Filament\Resources\SchoolClosures\Pages;

use App\Filament\Resources\SchoolClosures\Actions\SuggestHolidaysAction;
use App\Filament\Resources\SchoolClosures\SchoolClosureResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSchoolClosures extends ListRecords
{
    protected static string $resource = SchoolClosureResource::class;

    /**
     * Creating and editing run as a modal over the list: a period has four
     * fields, not worth a page of their own.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            SuggestHolidaysAction::make(),
            CreateAction::make(),
        ];
    }
}
