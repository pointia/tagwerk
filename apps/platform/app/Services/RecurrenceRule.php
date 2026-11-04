<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\LocalDates;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use RRule\RRule;

/**
 * Recurrence rules the way people say them.
 *
 * What gets stored stays an RFC 5545 rule. This class takes apart the shapes
 * the form offers — daily, on weekdays, on a day of the month or the n-th
 * weekday, yearly — and describes them in the current language. Anything else counts as a
 * custom rule: it is only described, not taken apart.
 */
final readonly class RecurrenceRule
{
    /** The weekday codes of RFC 5545, in calendar order. */
    public const array DAYS = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];

    /** @return array<string, string> code → name */
    public static function weekdays(): array
    {
        return [
            'MO' => __('Monday'),
            'TU' => __('Tuesday'),
            'WE' => __('Wednesday'),
            'TH' => __('Thursday'),
            'FR' => __('Friday'),
            'SA' => __('Saturday'),
            'SU' => __('Sunday'),
        ];
    }

    /**
     * The positions "on the 2nd Thursday" can express; -1 is the last.
     *
     * @return array<int, string>
     */
    public static function positions(): array
    {
        return [1 => __('1st'), 2 => __('2nd'), 3 => __('3rd'), 4 => __('4th'), -1 => __('last')];
    }

    private const array FREQUENCIES = ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'];

    public int $interval;

    /** @var list<string> */
    public array $weekdays;

    /**
     * @param  'DAILY'|'WEEKLY'|'MONTHLY'|'YEARLY'  $frequency
     * @param  array<int, string>  $weekdays
     */
    public function __construct(
        public string $frequency,
        int $interval = 1,
        array $weekdays = [],
        public ?int $monthDay = null,
        public ?int $position = null,
        public ?string $positionWeekday = null,
    ) {
        $this->interval = max(1, $interval);

        // In calendar order, so equal rules are stored identically.
        $this->weekdays = array_values(array_intersect(self::DAYS, $weekdays));
    }

    /** Null when the rule can do more than the form takes apart. */
    public static function parse(?string $rule): ?self
    {
        $parts = [];

        foreach (array_filter(explode(';', strtoupper(trim((string) $rule)))) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $parts[$name] = $value;
        }

        $frequency = $parts['FREQ'] ?? '';
        $interval = $parts['INTERVAL'] ?? '1';
        $days = $parts['BYDAY'] ?? null;
        $monthDay = $parts['BYMONTHDAY'] ?? null;
        unset($parts['FREQ'], $parts['INTERVAL'], $parts['BYDAY'], $parts['BYMONTHDAY']);

        // COUNT, UNTIL, BYMONTH and friends stay custom rules.
        if ($parts !== [] || ! in_array($frequency, self::FREQUENCIES, true)
            || ! ctype_digit($interval) || (int) $interval < 1) {
            return null;
        }

        return match ($frequency) {
            'DAILY', 'YEARLY' => $days === null && $monthDay === null
                ? new self($frequency, (int) $interval)
                : null,
            'WEEKLY' => $monthDay === null ? self::weekly((int) $interval, $days) : null,
            'MONTHLY' => self::monthly((int) $interval, $days, $monthDay),
        };
    }

    /** Describes any rule, including empty and custom ones. */
    public static function describeRule(?string $rule, ?CarbonImmutable $start = null): string
    {
        if ($rule === null || trim($rule) === '') {
            return __('Once');
        }

        $simple = self::parse($rule);

        if ($simple !== null) {
            return $simple->describe($start);
        }

        if (! self::isValid($rule)) {
            return __('Invalid rule');
        }

        return ucfirst(self::rrule($rule, $start ?? CarbonImmutable::today())->humanReadable([
            'locale' => app()->getLocale(),
            'include_start' => false,
            'explicit_infinite' => false,
        ]));
    }

    public static function isValid(string $rule): bool
    {
        // Only the RRULE part: line breaks would otherwise smuggle in DTSTART or EXDATE.
        if (preg_match('/^[A-Z0-9=;,+\-]+$/i', $rule) !== 1) {
            return false;
        }

        try {
            self::rrule($rule, CarbonImmutable::today());
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /**
     * The next dates from a given day, for the preview in the form.
     *
     * @return list<CarbonImmutable>
     */
    public static function upcoming(string $rule, CarbonImmutable $start, CarbonImmutable $after, int $count): array
    {
        $dates = self::rrule($rule, $start)->getOccurrencesAfter($after->startOfDay()->toDateTime(), true, $count);

        return array_values(array_map(
            fn (DateTimeInterface $date): CarbonImmutable => CarbonImmutable::parse($date->format('Y-m-d')),
            $dates,
        ));
    }

    public function toRrule(): string
    {
        $parts = ['FREQ='.$this->frequency];

        if ($this->interval > 1) {
            $parts[] = 'INTERVAL='.$this->interval;
        }

        if ($this->weekdays !== []) {
            $parts[] = 'BYDAY='.implode(',', $this->weekdays);
        }

        if ($this->position !== null && $this->positionWeekday !== null) {
            $parts[] = 'BYDAY='.$this->position.$this->positionWeekday;
        }

        if ($this->monthDay !== null) {
            $parts[] = 'BYMONTHDAY='.$this->monthDay;
        }

        return implode(';', $parts);
    }

    /**
     * Without its own details the rule follows its start — "weekly" from a
     * Monday means every Monday. With a start that gets spelled out.
     */
    public function describe(?CarbonImmutable $start = null): string
    {
        return match ($this->frequency) {
            'DAILY' => $this->interval === 1 ? __('Daily') : __('Every :count days', ['count' => $this->interval]),
            'WEEKLY' => $this->describeWeekly($start),
            'MONTHLY' => $this->describeMonthly($start),
            default => $this->describeYearly($start),
        };
    }

    private function describeWeekly(?CarbonImmutable $start): string
    {
        $days = $this->weekdays !== [] || $start === null ? $this->weekdays : [self::code($start)];

        if ($this->interval > 1) {
            return $days === []
                ? __('Every :count weeks', ['count' => $this->interval])
                : __('Every :count weeks on :days', ['count' => $this->interval, 'days' => self::enumerate($days)]);
        }

        return match (true) {
            $days === [] => __('Weekly'),
            count($days) === 7 => __('Daily'),
            $days === ['MO', 'TU', 'WE', 'TH', 'FR'] => __('On weekdays'),
            $days === ['SA', 'SU'] => __('At the weekend'),
            default => __('Every :days', ['days' => self::enumerate($days)]),
        };
    }

    private function describeMonthly(?CarbonImmutable $start): string
    {
        $when = match (true) {
            $this->position !== null && $this->positionWeekday !== null => __('on the :position :weekday', [
                'position' => self::positions()[$this->position],
                'weekday' => self::weekdays()[$this->positionWeekday],
            ]),
            $this->monthDay !== null => __('on day :day', ['day' => $this->monthDay]),
            $start !== null => __('on day :day', ['day' => $start->day]),
            default => null,
        };

        if ($when === null) {
            return $this->interval === 1 ? __('Monthly') : __('Every :count months', ['count' => $this->interval]);
        }

        return ($this->interval === 1 ? __('Every month') : __('Every :count months', ['count' => $this->interval])).' '.$when;
    }

    private function describeYearly(?CarbonImmutable $start): string
    {
        $base = $this->interval === 1 ? __('Yearly') : __('Every :count years', ['count' => $this->interval]);

        return $start === null ? $base : $base.' '.__('on :date', [
            'date' => LocalDates::dayMonth($start),
        ]);
    }

    private static function weekly(int $interval, ?string $days): ?self
    {
        if ($days === null) {
            return new self('WEEKLY', $interval);
        }

        $list = explode(',', $days);

        if (array_diff($list, self::DAYS) !== []) {
            return null;
        }

        return new self('WEEKLY', $interval, $list);
    }

    private static function monthly(int $interval, ?string $days, ?string $monthDay): ?self
    {
        if ($days !== null && $monthDay !== null) {
            return null;
        }

        if ($monthDay !== null) {
            return ctype_digit($monthDay) && (int) $monthDay >= 1 && (int) $monthDay <= 31
                ? new self('MONTHLY', $interval, monthDay: (int) $monthDay)
                : null;
        }

        if ($days === null) {
            return new self('MONTHLY', $interval);
        }

        if (preg_match('/^(\+?[1-4]|-1)(MO|TU|WE|TH|FR|SA|SU)$/', $days, $match) !== 1) {
            return null;
        }

        return new self('MONTHLY', $interval, position: (int) $match[1], positionWeekday: $match[2]);
    }

    private static function rrule(string $rule, CarbonImmutable $start): RRule
    {
        return new RRule('DTSTART:'.$start->format('Ymd').PHP_EOL.'RRULE:'.$rule);
    }

    private static function code(CarbonImmutable $day): string
    {
        return self::DAYS[$day->dayOfWeekIso - 1];
    }

    /** @param list<string> $days */
    private static function enumerate(array $days): string
    {
        $names = array_map(fn (string $day): string => self::weekdays()[$day], $days);
        $last = array_pop($names);

        return $names === [] ? $last : implode(', ', $names).' '.__('and').' '.$last;
    }
}
