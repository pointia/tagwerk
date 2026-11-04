<?php

declare(strict_types=1);

namespace App\Integrations\Weather\OpenMeteo;

/**
 * The WMO weather codes as words and a symbol.
 *
 * Grouped instead of one by one: on a wall nobody distinguishes "moderate"
 * from "heavy" snowfall — the jacket is thick either way.
 */
final class WmoCodes
{
    public static function label(int $code): string
    {
        return match (true) {
            $code === 0 => __('Clear'),
            $code === 1 => __('Mostly clear'),
            $code === 2 => __('Partly cloudy'),
            $code === 3 => __('Cloudy'),
            $code <= 48 => __('Fog'),
            $code <= 57 => __('Drizzle'),
            $code <= 67 => __('Rain'),
            $code <= 77 => __('Snow'),
            $code <= 82 => __('Showers'),
            $code <= 86 => __('Snow showers'),
            default => __('Thunderstorm'),
        };
    }

    /** At night a clear sky shows the moon — a sun at 23:00 reads like an error. */
    public static function symbol(int $code, bool $day = true): string
    {
        return match (true) {
            $code <= 1 && ! $day => '🌙',
            $code === 0 => '☀️',
            $code === 1 => '🌤️',
            $code === 2 => '⛅',
            $code === 3 => '☁️',
            $code <= 48 => '🌫️',
            $code <= 57 => '🌦️',
            $code <= 67 => '🌧️',
            $code <= 77 => '🌨️',
            $code <= 82 => '🌦️',
            $code <= 86 => '🌨️',
            default => '⛈️',
        };
    }

    /**
     * The code that stands for a whole day.
     *
     * Open-Meteo's own daily code is the day's worst hour: one cloudy hour
     * at dawn turns a sunny day into "cloudy". Here the daylight hours vote
     * instead — the most frequent code wins, but rain, snow or thunder in at
     * least a quarter of them take precedence, because whoever plans the
     * day needs to know about the shower more than about the sun around it.
     *
     * @param  list<int>  $codes  the codes of the daylight hours
     */
    public static function forDay(array $codes, int $fallback): int
    {
        if ($codes === []) {
            return $fallback;
        }

        $wet = array_values(array_filter($codes, static fn (int $code): bool => $code >= 51));

        $voters = count($wet) * 4 >= count($codes) ? $wet : $codes;
        $counts = array_count_values($voters);

        // On a tie the milder code wins: two readings of the same day, the
        // friendlier one is at least as likely.
        ksort($counts);
        arsort($counts);

        return (int) array_key_first($counts);
    }

    /**
     * A dry sky judged by the sunshine that gets through, not by the cloud
     * cover alone.
     *
     * The WMO code counts high veil clouds as "overcast", although the sun
     * shines through them in full: a day with eight hours of sunshine came
     * out as clouds all day. Fog, rain and anything wetter stay as they are.
     *
     * @param  float|null  $share  sunshine as a share of the daylight, 0 to 1
     */
    public static function bySunshine(int $code, ?float $share): int
    {
        if ($share === null || $code > 3) {
            return $code;
        }

        return match (true) {
            $share >= 0.8 => 0,
            $share >= 0.6 => 1,
            $share >= 0.3 => 2,
            default => 3,
        };
    }
}
