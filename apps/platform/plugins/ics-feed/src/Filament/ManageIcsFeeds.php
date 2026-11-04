<?php

declare(strict_types=1);

namespace Tagwerk\IcsFeed\Filament;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Tagwerk\IcsFeed\IcsFeed;

class ManageIcsFeeds extends ManageRecords
{
    protected static string $resource = IcsFeedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->after(fn (IcsFeed $record) => IcsFeedResource::announce($record->renew())),
        ];
    }
}
