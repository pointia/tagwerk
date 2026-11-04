<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ThemeMode;
use App\Enums\ThemePreference;
use App\Models\Household;
use Carbon\CarbonImmutable;

/**
 * When the wall and the phone turn light or dark.
 *
 * Computed here and not in the browser: the UIs get finished points in time
 * and only compare them with the clock. Otherwise every UI would have to
 * reproduce sun position, offset and the household's time zone itself — and
 * the wall might compute in the device's zone.
 */
final class ThemeSchedule
{
    /**
     * @return array{theme: 'light'|'dark', light_from: CarbonImmutable, dark_from: CarbonImmutable, next_change: CarbonImmutable|null}
     */
    public function state(Household $household, ThemePreference $preference, CarbonImmutable $now): array
    {
        $zone = $household->timezone ?? (string) config('app.timezone');
        $now = $now->setTimezone($zone);
        $today = $this->day($household, $now->startOfDay());

        // A fixed choice has no scheduled change any more.
        if ($preference !== ThemePreference::Auto) {
            return [
                'theme' => $preference === ThemePreference::Dark ? 'dark' : 'light',
                ...$today,
                'next_change' => null,
            ];
        }

        /*
         * Three sections, not two: before the light moment it is still last
         * night. Whoever only checks "after the dark moment" shows the light
         * theme at five in the morning.
         */
        if ($now->lt($today['light_from'])) {
            return ['theme' => 'dark', ...$today, 'next_change' => $today['light_from']];
        }

        if ($now->lt($today['dark_from'])) {
            return ['theme' => 'light', ...$today, 'next_change' => $today['dark_from']];
        }

        return [
            'theme' => 'dark',
            ...$today,
            'next_change' => $this->day($household, $now->startOfDay()->addDay())['light_from'],
        ];
    }

    /** @return array{light_from: CarbonImmutable, dark_from: CarbonImmutable} */
    private function day(Household $household, CarbonImmutable $day): array
    {
        return $this->sunTimes($household, $day) ?? $this->fixedTimes($household, $day);
    }

    /** @return array{light_from: CarbonImmutable, dark_from: CarbonImmutable}|null */
    private function sunTimes(Household $household, CarbonImmutable $day): ?array
    {
        if ($household->theme_mode !== ThemeMode::Sun
            || $household->latitude === null
            || $household->longitude === null) {
            return null;
        }

        // Noon instead of midnight: that way the result surely belongs to this
        // calendar day and not to the previous day in UTC.
        $info = date_sun_info(
            $day->setTime(12, 0)->getTimestamp(),
            (float) $household->latitude,
            (float) $household->longitude,
        );

        /*
         * Beyond the polar circles the sun does not rise or set on some days;
         * date_sun_info then returns true or false instead of a time. The
         * fixed times apply then, instead of no change at all.
         */
        if (! is_int($info['sunrise']) || ! is_int($info['sunset'])) {
            return null;
        }

        $zone = $day->getTimezone();

        return [
            'light_from' => CarbonImmutable::createFromTimestamp($info['sunrise'], $zone)
                ->startOfMinute()
                ->addMinutes($household->sunrise_offset),
            'dark_from' => CarbonImmutable::createFromTimestamp($info['sunset'], $zone)
                ->startOfMinute()
                ->addMinutes($household->sunset_offset),
        ];
    }

    /** @return array{light_from: CarbonImmutable, dark_from: CarbonImmutable} */
    private function fixedTimes(Household $household, CarbonImmutable $day): array
    {
        return [
            'light_from' => $this->timeOn($day, (string) $household->theme_light_at),
            'dark_from' => $this->timeOn($day, (string) $household->theme_dark_at),
        ];
    }

    private function timeOn(CarbonImmutable $day, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_map(intval(...), explode(':', $time));

        return $day->setTime($hour, $minute);
    }
}
