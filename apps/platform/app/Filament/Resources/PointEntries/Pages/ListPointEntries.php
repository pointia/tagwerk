<?php

declare(strict_types=1);

namespace App\Filament\Resources\PointEntries\Pages;

use App\Filament\Resources\PointEntries\PointEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListPointEntries extends ListRecords
{
    protected static string $resource = PointEntryResource::class;
}
