<?php

namespace App\Filament\Resources\Checklists\Pages;

use App\Filament\Resources\Checklists\ChecklistResource;
use App\Jobs\SyncListJob;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditChecklist extends EditRecord
{
    protected static string $resource = ChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** Linked by hand: sync now instead of at the next scheduled run. */
    protected function afterSave(): void
    {
        if ($this->record->wasChanged(['list_provider', 'list_account_id', 'list_target']) && $this->record->list_account_id !== null) {
            SyncListJob::dispatch((int) $this->record->getKey(), first: true);
        }
    }
}
