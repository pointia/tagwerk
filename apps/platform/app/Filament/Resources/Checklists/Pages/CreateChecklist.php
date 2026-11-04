<?php

namespace App\Filament\Resources\Checklists\Pages;

use App\Filament\Resources\Checklists\ChecklistResource;
use App\Jobs\SyncListJob;
use Filament\Resources\Pages\CreateRecord;

class CreateChecklist extends CreateRecord
{
    protected static string $resource = ChecklistResource::class;

    /** Linked by hand: sync now instead of at the next scheduled run. */
    protected function afterCreate(): void
    {
        if ($this->record->list_account_id !== null) {
            SyncListJob::dispatch((int) $this->record->getKey(), first: true);
        }
    }
}
