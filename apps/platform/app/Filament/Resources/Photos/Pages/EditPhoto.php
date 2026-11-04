<?php

declare(strict_types=1);

namespace App\Filament\Resources\Photos\Pages;

use App\Filament\Resources\Photos\PhotoResource;
use Filament\Resources\Pages\EditRecord;

class EditPhoto extends EditRecord
{
    protected static string $resource = PhotoResource::class;
}
