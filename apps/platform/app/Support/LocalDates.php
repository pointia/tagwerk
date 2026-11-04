<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use IntlDateFormatter;
use IntlDatePatternGenerator;

/**
 * Dates as the current language writes them.
 *
 * German and English keep the formats they always had; every other language
 * gets ICU's best pattern for its tag — a pack brings no formats of its own.
 */
final class LocalDates
{
    /** PHP `date()` format for a date in the admin. */
    public static function phpDate(): string
    {
        return match (app()->getLocale()) {
            'de' => 'd.m.Y',
            'en' => 'M j, Y',
            default => self::toPhp(self::pattern('yMMdd')),
        };
    }

    /** PHP `date()` format for a date with time in the admin. */
    public static function phpDateTime(): string
    {
        return match (app()->getLocale()) {
            'de' => 'd.m.Y H:i',
            'en' => 'M j, Y g:i A',
            default => self::toPhp(self::pattern('yMMddjmm')),
        };
    }

    /** “15. Jänner”, “January 15”, “15 janvier”. */
    public static function dayMonth(DateTimeInterface $date): string
    {
        return self::format($date, 'D. MMMM', 'MMMM D', 'dMMMM');
    }

    /** “Mo 15. Jän.”, “Mon, Jan 15”, “lun. 15 janv.”. */
    public static function weekdayDayMonth(DateTimeInterface $date): string
    {
        return self::format($date, 'dd D. MMM', 'ddd, MMM D', 'EEEdMMM');
    }

    private static function format(DateTimeInterface $date, string $german, string $english, string $skeleton): string
    {
        $locale = app()->getLocale();

        if ($locale === 'de' || $locale === 'en') {
            return CarbonImmutable::instance($date)->locale($locale)->isoFormat($locale === 'de' ? $german : $english);
        }

        $formatter = new IntlDateFormatter(
            self::icuLocale(),
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $date->getTimezone(),
            IntlDateFormatter::GREGORIAN,
            self::pattern($skeleton),
        );

        return (string) $formatter->format($date);
    }

    private static function pattern(string $skeleton): string
    {
        return (string) (new IntlDatePatternGenerator(self::icuLocale()))->getBestPattern($skeleton);
    }

    private static function icuLocale(): string
    {
        return str_replace('-', '_', Locale::intlTag(app()->getLocale()));
    }

    /**
     * An ICU pattern of numeric fields as a PHP `date()` format. Only what
     * `phpDate()`/`phpDateTime()` ask ICU for can appear; quoted literals
     * become escaped characters.
     */
    private static function toPhp(string $pattern): string
    {
        $tokens = [
            'yyyy' => 'Y', 'yy' => 'y', 'y' => 'Y',
            'MM' => 'm', 'M' => 'n',
            'dd' => 'd', 'd' => 'j',
            'HH' => 'H', 'H' => 'G', 'hh' => 'h', 'h' => 'g',
            'mm' => 'i', 'a' => 'A',
        ];

        $php = '';

        foreach (preg_split("/('[^']*'|y+|M+|d+|H+|h+|m+|a)/", $pattern, flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if (isset($tokens[$part])) {
                $php .= $tokens[$part];
            } else {
                $literal = str_starts_with($part, "'") ? trim($part, "'") : $part;
                $php .= preg_replace('/([A-Za-z\\\\])/', '\\\\$1', $literal);
            }
        }

        return $php;
    }
}
