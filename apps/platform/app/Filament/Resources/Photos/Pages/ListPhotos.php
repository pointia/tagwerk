<?php

declare(strict_types=1);

namespace App\Filament\Resources\Photos\Pages;

use App\Filament\Resources\Photos\PhotoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPhotos extends ListRecords
{
    protected static string $resource = PhotoResource::class;

    /** @return array<int, CreateAction> */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('Upload photo')),
        ];
    }
}
