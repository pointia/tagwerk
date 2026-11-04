<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // With history, deactivate instead of delete: the earned stars
            // hang on the completions.
            DeleteAction::make()
                ->using(static function (Task $record): bool {
                    $record->retire();

                    return true;
                }),
        ];
    }
}
