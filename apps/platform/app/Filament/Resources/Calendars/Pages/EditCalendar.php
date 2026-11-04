<?php

declare(strict_types=1);

namespace App\Filament\Resources\Calendars\Pages;

use App\Filament\Resources\Calendars\CalendarResource;
use Filament\Resources\Pages\EditRecord;

class EditCalendar extends EditRecord
{
    protected static string $resource = CalendarResource::class;

    protected function getSavedNotificationTitle(): ?string
    {
        return __('Calendar saved');
    }
}
