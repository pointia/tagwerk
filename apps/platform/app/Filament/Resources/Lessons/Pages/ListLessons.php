<?php

declare(strict_types=1);

namespace App\Filament\Resources\Lessons\Pages;

use App\Filament\Resources\Lessons\LessonResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListLessons extends ListRecords
{
    protected static string $resource = LessonResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('grid')
                ->label(__('Grid'))
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->url(LessonResource::getUrl('index')),
            CreateAction::make(),
        ];
    }
}
