<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RecurrenceRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RecurrenceRuleTest extends TestCase
{
    /** @return array<string, array{string|null, string}> */
    public static function descriptions(): array
    {
        return [
            'empty' => [null, 'Einmalig'],
            'daily' => ['FREQ=DAILY', 'Täglich'],
            'every third day' => ['FREQ=DAILY;INTERVAL=3', 'Alle 3 Tage'],
            'weekdays' => ['FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR', 'Werktags'],
            'weekend' => ['FREQ=WEEKLY;BYDAY=SU,SA', 'Am Wochenende'],
            'one day' => ['FREQ=WEEKLY;BYDAY=MO', 'Jeden Montag'],
            'two days' => ['FREQ=WEEKLY;BYDAY=TH,MO', 'Jeden Montag und Donnerstag'],
            'three days' => ['FREQ=WEEKLY;BYDAY=MO,WE,FR', 'Jeden Montag, Mittwoch und Freitag'],
            'every second week' => ['FREQ=WEEKLY;INTERVAL=2;BYDAY=SA', 'Alle 2 Wochen am Samstag'],
            'weekly from start' => ['FREQ=WEEKLY', 'Jeden Dienstag'],
            'day of month' => ['FREQ=MONTHLY;BYMONTHDAY=15', 'Jeden Monat am 15.'],
            'month from start' => ['FREQ=MONTHLY', 'Jeden Monat am 15.'],
            'second Thursday' => ['FREQ=MONTHLY;BYDAY=+2TH', 'Jeden Monat am 2. Donnerstag'],
            'last Friday' => ['FREQ=MONTHLY;INTERVAL=3;BYDAY=-1FR', 'Alle 3 Monate am letzten Freitag'],
            'yearly' => ['FREQ=YEARLY', 'Jährlich am 15. September'],
            'custom rule' => ['FREQ=DAILY;COUNT=5', 'Täglich, 5 Mal insgesamt'],
            'broken' => ['FREQ=SOMETIME', 'Ungültige Regel'],
        ];
    }

    #[DataProvider('descriptions')]
    public function test_describes_rules_in_german(?string $rule, string $expected): void
    {
        // A Tuesday, the 15th.
        $start = CarbonImmutable::parse('2026-09-15');

        $this->assertSame($expected, RecurrenceRule::describeRule($rule, $start));
    }

    /** @return array<string, array{string, string}> */
    public static function englishDescriptions(): array
    {
        return [
            'two days' => ['FREQ=WEEKLY;BYDAY=TH,MO', 'Every Monday and Thursday'],
            'second Thursday' => ['FREQ=MONTHLY;BYDAY=+2TH', 'Every month on the 2nd Thursday'],
            'day month' => ['FREQ=MONTHLY;BYMONTHDAY=15', 'Every month on day 15'],
            'yearly' => ['FREQ=YEARLY', 'Yearly on September 15'],
            'custom rule' => ['FREQ=DAILY;COUNT=5', 'Daily, 5 times'],
        ];
    }

    #[DataProvider('englishDescriptions')]
    public function test_describes_rules_in_english(string $rule, string $expected): void
    {
        App::setLocale('en');

        $this->assertSame($expected, RecurrenceRule::describeRule($rule, CarbonImmutable::parse('2026-09-15')));
    }

    public function test_without_start_the_description_stays_general(): void
    {
        $this->assertSame('Wöchentlich', RecurrenceRule::describeRule('FREQ=WEEKLY'));
        $this->assertSame('Monatlich', RecurrenceRule::describeRule('FREQ=MONTHLY'));
    }

    /** @return array<string, array{string, string}> */
    public static function spellings(): array
    {
        return [
            'days sorted' => ['FREQ=WEEKLY;BYDAY=FR,MO', 'FREQ=WEEKLY;BYDAY=MO,FR'],
            'interval 1 dropped' => ['FREQ=DAILY;INTERVAL=1', 'FREQ=DAILY'],
            'sign dropped' => ['FREQ=MONTHLY;BYDAY=+2TH', 'FREQ=MONTHLY;BYDAY=2TH'],
            'lower case' => ['freq=monthly;interval=2;bymonthday=1', 'FREQ=MONTHLY;INTERVAL=2;BYMONTHDAY=1'],
        ];
    }

    #[DataProvider('spellings')]
    public function test_writes_rules_uniformly(string $rule, string $expected): void
    {
        $this->assertSame($expected, RecurrenceRule::parse($rule)?->toRrule());
    }

    public function test_parses_only_what_the_form_supports(): void
    {
        $this->assertNull(RecurrenceRule::parse('FREQ=DAILY;COUNT=5'));
        $this->assertNull(RecurrenceRule::parse('FREQ=MONTHLY;BYMONTHDAY=-1'));
        $this->assertNull(RecurrenceRule::parse('FREQ=MONTHLY;BYDAY=MO,TU'));
        $this->assertNull(RecurrenceRule::parse('FREQ=WEEKLY;BYDAY=XX'));
        $this->assertNull(RecurrenceRule::parse(''));
    }

    public function test_lists_the_next_dates(): void
    {
        $dates = RecurrenceRule::upcoming(
            'FREQ=WEEKLY;BYDAY=MO,TH',
            CarbonImmutable::parse('2026-09-01'),
            CarbonImmutable::parse('2026-09-15'),
            3,
        );

        $this->assertSame(
            ['2026-09-17', '2026-09-21', '2026-09-24'],
            array_map(fn (CarbonImmutable $day): string => $day->toDateString(), $dates),
        );
    }
}
