<?php

declare(strict_types=1);

namespace App\Filament\Resources\Connections\Pages;

use App\Filament\Resources\Connections\Actions\ConnectServiceAction;
use App\Filament\Resources\Connections\ConnectionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListConnections extends ListRecords
{
    protected static string $resource = ConnectionResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [ConnectServiceAction::make()];
    }
}
