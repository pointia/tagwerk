<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LessonCadence;
use App\Models\Household;
use App\Models\Lesson;
use App\Models\LessonPeriod;
use App\Models\Person;
use App\Models\SchoolClosure;
use App\Support\HouseholdContext;
use Illuminate\Database\Seeder;

/**
 * The timetable of both children.
 *
 * Deliberately uneven: Lena has a bell schedule, Max does not; one lesson
 * runs every other calendar week, one every other school week. Otherwise it
 * would go unnoticed if only the simplest rule worked.
 */
final class SchoolSeeder extends Seeder
{
    /** The Monday the cadence of the fortnightly lessons hangs on. */
    private const ANCHOR = '2026-09-07';

    public function run(): void
    {
        $household = Household::firstOrFail();

        app(HouseholdContext::class)->setId($household->id);

        $people = Person::query()->pluck('id', 'name');

        $lena = (int) $people['Lena'];
        $max = (int) $people['Max'];

        $periods = [
            [1, '07:50', '08:40'],
            [2, '08:45', '09:35'],
            [3, '09:55', '10:45'],
            [4, '10:50', '11:40'],
            [5, '11:50', '12:40'],
            [6, '12:45', '13:35'],
        ];

        foreach ($periods as [$number, $from, $to]) {
            LessonPeriod::create([
                'person_id' => $lena,
                'number' => $number,
                'starts_at' => $from,
                'ends_at' => $to,
            ]);
        }

        // Lena: lessons from the bell schedule, half a morning per weekday.
        $lenaPlan = [
            [1, 1, 'Mathematik'], [1, 2, 'Deutsch'], [1, 3, 'Turnen'],
            [2, 1, 'Englisch'], [2, 2, 'Mathematik'], [2, 3, 'Sachunterricht'],
            [3, 1, 'Deutsch'], [3, 2, 'Musik'],
            [4, 1, 'Mathematik'], [4, 2, 'Englisch'], [4, 3, 'Werken'],
            [5, 1, 'Deutsch'], [5, 2, 'Sachunterricht'],
        ];

        foreach ($lenaPlan as [$weekday, $number, $subject]) {
            Lesson::create([
                'person_id' => $lena,
                'title' => $subject,
                'weekday' => $weekday,
                'period_number' => $number,
                'anchor_on' => self::ANCHOR,
            ]);
        }

        // Choir every other calendar week — it simply drops out during holidays.
        Lesson::create([
            'person_id' => $lena,
            'title' => 'Chor',
            'weekday' => 3,
            'period_number' => 3,
            'interval' => 2,
            'cadence' => LessonCadence::Calendar,
            'anchor_on' => self::ANCHOR,
        ]);

        // Max: no numbers, custom times — so the view looks usable even
        // without a stored bell schedule.
        $maxPlan = [
            [1, 'Mathematik', '08:00', '08:50'],
            [1, 'Geografie', '08:55', '09:45'],
            [2, 'Deutsch', '08:00', '08:50'],
            [3, 'Biologie', '08:00', '08:50'],
            [3, 'Mathematik', '08:55', '09:45'],
            [4, 'Englisch', '08:00', '08:50'],
            [5, 'Deutsch', '08:00', '08:50'],
        ];

        foreach ($maxPlan as [$weekday, $subject, $from, $to]) {
            Lesson::create([
                'person_id' => $max,
                'title' => $subject,
                'weekday' => $weekday,
                'starts_at' => $from,
                'ends_at' => $to,
                'anchor_on' => self::ANCHOR,
            ]);
        }

        // Workshop every other school week: holidays do not count.
        Lesson::create([
            'person_id' => $max,
            'title' => 'Werkstatt',
            'weekday' => 4,
            'starts_at' => '14:00',
            'ends_at' => '15:40',
            'interval' => 2,
            'cadence' => LessonCadence::SchoolWeeks,
            'anchor_on' => self::ANCHOR,
        ]);

        SchoolClosure::create([
            'person_id' => null,
            'title' => 'Herbstferien',
            'starts_on' => '2026-10-26',
            'ends_on' => '2026-10-31',
        ]);

        SchoolClosure::create([
            'person_id' => $lena,
            'title' => 'Schulautonomer Tag',
            'starts_on' => '2026-11-02',
            'ends_on' => '2026-11-02',
        ]);
    }
}
