<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\DeviceKind;
use App\Filament\Resources\Devices\DeviceResource;
use App\Filament\Resources\People\PersonResource;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Device;
use App\Models\Household;
use App\Models\Person;
use App\Services\TaskOccurrenceService;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** The first look into the admin: what is due today, is everything connected? */
final class HouseholdOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return app(HouseholdContext::class)->hasId();
    }

    protected function getHeading(): ?string
    {
        return __('Today in the household');
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $household = Household::query()->findOrFail(app(HouseholdContext::class)->id());
        $today = CarbonImmutable::now($household->timezone)->startOfDay();

        $occurrences = app(TaskOccurrenceService::class)->between($today, $today);
        $open = count(array_filter($occurrences, fn (array $occurrence): bool => $occurrence['completion'] === null));

        $tablets = Device::query()->where('kind', DeviceKind::Wall)->get();
        $paired = $tablets->whereNotNull('paired_at')->count();

        return [
            Stat::make(__('Open tasks'), $open)
                ->description(match (true) {
                    $occurrences === [] => __('Nothing on today'),
                    $open === 0 => __('All done'),
                    default => __('of :total today', ['total' => count($occurrences)]),
                })
                ->descriptionIcon($open === 0 ? Heroicon::OutlinedCheckBadge : Heroicon::OutlinedCheckCircle)
                ->color($open === 0 ? 'success' : 'primary')
                ->url(TaskResource::getUrl()),

            Stat::make(__('People'), Person::query()->count())
                ->description(__('in the family'))
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->url(PersonResource::getUrl()),

            Stat::make(__('Wall tablets'), $paired)
                ->description(match (true) {
                    $tablets->isEmpty() => __('None created yet'),
                    $paired === $tablets->count() => __('All paired'),
                    default => trans_choice(':count waiting to be paired|:count waiting to be paired', $tablets->count() - $paired),
                })
                ->descriptionIcon($paired > 0 ? Heroicon::OutlinedSignal : Heroicon::OutlinedSignalSlash)
                ->color($tablets->isNotEmpty() && $paired === $tablets->count() ? 'success' : 'warning')
                ->url(DeviceResource::getUrl()),
        ];
    }
}
