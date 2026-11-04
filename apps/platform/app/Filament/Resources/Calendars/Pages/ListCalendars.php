<?php

declare(strict_types=1);

namespace App\Filament\Resources\Calendars\Pages;

use App\Filament\Resources\Calendars\CalendarResource;
use App\Integrations\Calendar\Builtin\BuiltinCalendarProvider;
use App\Models\Calendar;
use App\Models\Person;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCalendars extends ListRecords
{
    protected static string $resource = CalendarResource::class;

    /**
     * The hint about the owner mapping — the setting most often overlooked.
     *
     * It only shows while **no** calendar belongs to a person: "belongs to
     * nobody" is the right state for a family calendar, and a hint pointing
     * at it permanently would be invisible after a week. Once the household
     * has used the mapping, it knows about it.
     *
     * The built-in calendar is left out. It is called "Familie" and applies
     * to everyone — counting it would nag about something that is right.
     */
    public function getSubheading(): ?string
    {
        if (Person::query()->doesntExist() || Calendar::query()->has('people')->exists()) {
            return null;
        }

        $unowned = Calendar::query()
            ->where('is_active', true)
            ->whereHas('source', fn (Builder $query) => $query->where('provider', '!=', BuiltinCalendarProvider::KEY))
            ->count();

        if ($unowned === 0) {
            return null;
        }

        return trans_choice(
            'One calendar belongs to nobody yet. Its events appear on the wall in the calendar colour and count for no person — “Belongs to” changes that per calendar.|:count calendars belong to nobody yet. Their events appear on the wall in the calendar colour and count for no person — “Belongs to” changes that per calendar.',
            $unowned,
        );
    }
}
