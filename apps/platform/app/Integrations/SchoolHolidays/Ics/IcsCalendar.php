<?php

declare(strict_types=1);

namespace App\Integrations\SchoolHolidays\Ics;

use Carbon\CarbonImmutable;

/**
 * Reads the all-day events from an iCalendar text (RFC 5545).
 *
 * Deliberately only as much as a holiday calendar needs: folded lines,
 * escaped characters, DTSTART/DTEND as dates. Recurrence rules and time zones
 * are missing — nobody publishes holidays as a series.
 */
final class IcsCalendar
{
    /**
     * @return array<int, array{title: string, start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function allDayEvents(string $ics): array
    {
        // Unfold: a continuation line starts with a space or tab.
        $lines = preg_split('/\r?\n/', (string) preg_replace('/\r?\n[ \t]/', '', $ics)) ?: [];

        $events = [];
        $current = null;

        foreach ($lines as $line) {
            if ($line === 'BEGIN:VEVENT') {
                $current = [];

                continue;
            }

            if ($line === 'END:VEVENT') {
                $event = $current === null ? null : $this->event($current);

                if ($event !== null) {
                    $events[] = $event;
                }

                $current = null;

                continue;
            }

            if ($current === null || ! str_contains($line, ':')) {
                continue;
            }

            [$head, $value] = explode(':', $line, 2);
            $name = strtoupper(explode(';', $head, 2)[0]);

            $current[$name] = $value;
        }

        return $events;
    }

    /**
     * @param  array<string, string>  $fields
     * @return array{title: string, start: CarbonImmutable, end: CarbonImmutable}|null
     */
    private function event(array $fields): ?array
    {
        $start = $this->date($fields['DTSTART'] ?? '');

        if ($start === null) {
            return null;
        }

        // DTEND is exclusive for all-day events.
        $end = $this->date($fields['DTEND'] ?? '')?->subDay() ?? $start;

        return [
            'title' => $this->text($fields['SUMMARY'] ?? __('Holiday break')),
            'start' => $start,
            'end' => $end->lessThan($start) ? $start : $end,
        ];
    }

    private function date(string $value): ?CarbonImmutable
    {
        if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $value, $parts) !== 1) {
            return null;
        }

        return CarbonImmutable::create((int) $parts[1], (int) $parts[2], (int) $parts[3]);
    }

    private function text(string $value): string
    {
        return trim(strtr($value, ['\\,' => ',', '\;' => ';', '\\n' => ' ', '\\N' => ' ', '\\\\' => '\\']));
    }
}
