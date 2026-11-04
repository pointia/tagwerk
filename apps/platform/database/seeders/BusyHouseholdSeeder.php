<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EventOrigin;
use App\Enums\ListKind;
use App\Enums\MealKind;
use App\Models\Calendar;
use App\Models\Checklist;
use App\Models\Event;
use App\Models\Household;
use App\Models\MealPlanEntry;
use App\Models\Person;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Services\PointLedger;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * A busy household — three weeks of operation instead of a handful of
 * examples.
 *
 * The other seeders show one entry per case; that does not answer whether a
 * week with six events a day is still readable. This one therefore fills up
 * to the limit on purpose: overlapping events, tasks every day, long lists.
 * Deterministic, so two runs produce the same picture and a difference in
 * appearance is down to the code.
 */
final class BusyHouseholdSeeder extends Seeder
{
    /** Before and after: paging back should not lead into emptiness. */
    private const DAYS_BEFORE = 7;

    private const DAYS_AFTER = 20;

    private CarbonImmutable $today;

    public function run(): void
    {
        $household = Household::query()->orderBy('id')->first();

        if ($household === null) {
            return;
        }

        app(HouseholdContext::class)->setId((int) $household->id);

        $this->today = CarbonImmutable::now($household->timezone)->startOfDay();

        $this->events();
        $this->tasks();
        $this->lists();
        $this->meals();
    }

    /**
     * Events across the whole period, several per day and some at the same
     * time.
     *
     * Simultaneous is the interesting case: only then does it show whether two
     * events at 4 pm fit side by side or one disappears.
     */
    private function events(): void
    {
        $calendars = Calendar::query()->get()->keyBy('summary');

        if ($calendars->isEmpty()) {
            return;
        }

        $templates = [
            'Familie' => [
                ['Großeltern zu Besuch', 15, 0, 180],
                ['Kino', 19, 30, 150],
                ['Flohmarkt', 9, 0, 120],
                ['Geburtstagsfeier', 14, 0, 240],
                ['Autowerkstatt', 8, 0, 90],
                ['Hausputz', 10, 0, 120],
            ],
            'Alex' => [
                ['Teambesprechung', 9, 30, 60],
                ['Mittagessen mit Chris', 12, 15, 60],
                ['Zahnarzt', 14, 15, 45],
                ['Laufrunde', 18, 0, 50],
                ['Videokonferenz Kunde', 16, 0, 45],
                ['Elternsprechtag', 17, 30, 30],
            ],
            'Sam' => [
                ['Yoga', 18, 0, 75],
                ['Schicht im Krankenhaus', 7, 0, 480],
                ['Chorprobe', 19, 30, 90],
                ['Friseur', 11, 0, 60],
                ['Elternabend', 19, 0, 90],
                ['Physiotherapie', 16, 30, 45],
            ],
            'Max' => [
                ['Fußballtraining', 16, 30, 90],
                ['Nachhilfe Mathe', 15, 0, 60],
                ['Schwimmen', 17, 0, 60],
                ['Bei Jonas spielen', 14, 30, 120],
                ['Zahnspange kontrollieren', 10, 30, 30],
                ['Turnier', 9, 0, 300],
            ],
            'Lena' => [
                ['Musikschule', 16, 0, 45],
                ['Ballett', 15, 30, 60],
                ['Kindergeburtstag', 14, 0, 180],
                ['Bücherei', 10, 0, 60],
                ['Reitstunde', 17, 0, 60],
                ['Logopädie', 11, 30, 45],
            ],
        ];

        $allDay = [
            [2, 'Familie', 'Wandertag'],
            [5, 'Max', 'Schulausflug'],
            [9, 'Familie', 'Umzug Oma'],
            [12, 'Lena', 'Kindergartenfest'],
            [-3, 'Familie', 'Besuch aus Wien'],
        ];

        for ($offset = -self::DAYS_BEFORE; $offset <= self::DAYS_AFTER; $offset++) {
            $day = $this->today->addDays($offset);

            foreach ($templates as $name => $options) {
                $calendar = $calendars->get($name);

                if ($calendar === null) {
                    continue;
                }

                // Two fixed patterns instead of randomness: every person has
                // something every day, and on some days two things at once.
                $picks = [abs($offset + 1) % count($options)];

                if (($offset + array_search($name, array_keys($templates), true)) % 3 === 0) {
                    $picks[] = (abs($offset) + 3) % count($options);
                }

                foreach (array_unique($picks) as $pick) {
                    [$title, $hour, $minute, $duration] = $options[$pick];
                    $start = $day->setTime($hour, $minute);

                    Event::create([
                        'calendar_id' => $calendar->id,
                        'title' => $title,
                        'starts_at' => $start,
                        'ends_at' => $start->addMinutes($duration),
                        'all_day' => false,
                        'origin' => EventOrigin::Remote,
                    ]);
                }
            }
        }

        foreach ($allDay as [$offset, $name, $title]) {
            $calendar = $calendars->get($name);

            if ($calendar === null) {
                continue;
            }

            $day = $this->today->addDays($offset);

            Event::create([
                'calendar_id' => $calendar->id,
                'title' => $title,
                'starts_at' => $day,
                'ends_at' => $day->addDay(),
                'all_day' => true,
                'origin' => EventOrigin::Remote,
            ]);
        }
    }

    /**
     * Routines for every day, plus a completed past.
     *
     * Without completions every progress bar would be at zero and every star
     * account empty — then one sees neither whether a full bar fits nor
     * whether three-digit stars break the tile.
     */
    private function tasks(): void
    {
        $people = Person::query()->get()->keyBy('name');
        $ledger = app(PointLedger::class);

        $templates = [
            ['Brotzeit vorbereiten', 'FREQ=DAILY', 2, 'Sam'],
            ['Tisch abräumen', 'FREQ=DAILY', 2, 'Max'],
            ['Schultasche packen', 'FREQ=WEEKLY;BYDAY=SU,MO,TU,WE,TH', 3, 'Max'],
            ['Hausaufgaben', 'FREQ=WEEKLY;BYDAY=MO,TU,WE,TH', 5, 'Lena'],
            ['Katze füttern', 'FREQ=DAILY', 2, 'Max'],
            ['Biomüll leeren', 'FREQ=WEEKLY;BYDAY=TH', 4, 'Max'],
            ['Schreibtisch aufräumen', 'FREQ=WEEKLY;BYDAY=SA', 6, 'Lena'],
            ['Geschirrspüler ausräumen', 'FREQ=DAILY', 3, null],
            ['Wäsche aufhängen', 'FREQ=WEEKLY;BYDAY=MO,TH', 4, 'Sam'],
            ['Einkaufen', 'FREQ=WEEKLY;BYDAY=FR', 6, 'Alex'],
            ['Blumen gießen', 'FREQ=WEEKLY;BYDAY=WE,SU', 3, 'Lena'],
            ['Bad putzen', 'FREQ=WEEKLY;BYDAY=SA', 8, 'Sam'],
            ['Staubsaugen', 'FREQ=WEEKLY;BYDAY=FR', 6, 'Alex'],
            ['Glascontainer', 'FREQ=MONTHLY;BYDAY=+3SA', 8, null],
            ['Fahrrad putzen', null, 4, 'Max'],
            ['Winterjacken hervorholen', null, 0, 'Sam'],
            ['Bücherei-Bücher zurückbringen', null, 3, 'Lena'],
        ];

        $position = 100;

        foreach ($templates as [$title, $rrule, $points, $name]) {
            $task = Task::create([
                'title' => $title,
                'rrule' => $rrule,
                'points' => $points,
                'person_id' => $name === null ? null : $people->get($name)?->id,
                'due_on' => $rrule === null
                    ? $this->today->addDays($position % 5)->toDateString()
                    : $this->today->subDays(self::DAYS_BEFORE)->toDateString(),
                'position' => $position++,
            ]);

            if ($task->person_id === null || $rrule === null) {
                continue;
            }

            $this->complete($task, $ledger);
        }
    }

    /**
     * Tick off the past days — not all of them.
     *
     * A gapless history would look staged and never show how a half-full bar
     * looks.
     */
    private function complete(Task $task, PointLedger $ledger): void
    {
        for ($offset = -self::DAYS_BEFORE; $offset <= 0; $offset++) {
            if (($offset + $task->position) % 4 === 0) {
                continue;
            }

            $day = $this->today->addDays($offset);

            $completion = TaskCompletion::create([
                'task_id' => $task->id,
                'person_id' => $task->person_id,
                'occurred_on' => $day->toDateString(),
                'completed_at' => $day->setTime(18, 30),
            ]);

            $ledger->awardForCompletion($completion);
        }
    }

    /** Long lists — the question is whether a column holds 20 rows. */
    private function lists(): void
    {
        $shopping = Checklist::query()->where('name', 'Einkauf')->first();

        if ($shopping !== null) {
            $position = 10;

            foreach ([
                ['Butter', '250 g'], ['Joghurt', '6 Becher'], ['Bananen', '1 kg'],
                ['Nudeln', '2 Packungen'], ['Passierte Tomaten', null], ['Olivenöl', null],
                ['Klopapier', '1 Packung'], ['Spülmittel', null], ['Käse', '300 g'],
                ['Eier', '10 Stück'], ['Haferflocken', null], ['Orangensaft', '2 l'],
                ['Waschmittel', null], ['Karotten', '1 kg'], ['Hühnerfilet', '600 g'],
                ['Tiefkühlerbsen', null], ['Müsliriegel', '1 Packung'], ['Zahnbürsten', '2 Stück'],
            ] as [$title, $quantity]) {
                $shopping->items()->create([
                    'title' => $title,
                    'quantity' => $quantity,
                    'done_at' => $position % 5 === 0 ? now()->subHours($position % 7 + 1) : null,
                    'position' => $position++,
                ]);
            }
        }

        $moreLists = [
            ['Schulsachen', ListKind::Shopping, '#7c3aed', [
                'Hefte A4 kariert', 'Füller', 'Buntstifte', 'Turnbeutel', 'Zirkel', 'Heftumschläge',
            ]],
            ['Urlaub Italien', ListKind::Packing, '#db2777', [
                'Reisepässe', 'Ladekabel', 'Sonnenhüte', 'Reiseapotheke', 'Schnorchel',
                'Kartenspiel', 'Luftmatratze', 'Strandtücher', 'Adapter',
            ]],
            ['Besorgungen', ListKind::Generic, '#0891b2', [
                'Paket zur Post', 'Schlüssel nachmachen', 'Versicherung anrufen',
                'Schuhe zum Schuster', 'Blumen für Oma',
            ]],
        ];

        $listPosition = 10;

        foreach ($moreLists as [$name, $kind, $color, $entries]) {
            $list = Checklist::create([
                'name' => $name,
                'kind' => $kind,
                'color' => $color,
                'position' => $listPosition++,
            ]);

            foreach ($entries as $index => $title) {
                $list->items()->create([
                    'title' => $title,
                    'done_at' => $index % 4 === 3 ? now()->subDay() : null,
                    'position' => $index,
                ]);
            }
        }
    }

    /** Three weeks of meal plan, so paging shows something too. */
    private function meals(): void
    {
        $dishes = [
            'Spaghetti Bolognese', 'Gemüsecurry', 'Fischstäbchen', 'Schnitzel',
            'Reste', 'Pizza', 'Eintopf', 'Kaiserschmarrn', 'Auflauf', 'Suppe',
            'Bei Oma essen', 'Wraps', 'Risotto', 'Gulasch',
        ];

        $start = $this->today->startOfWeek()->subWeek();

        for ($offset = 0; $offset < 21; $offset++) {
            $day = $start->addDays($offset);

            MealPlanEntry::create([
                'planned_on' => $day->toDateString(),
                'kind' => MealKind::Dinner,
                'title' => $dishes[$offset % count($dishes)],
            ]);

            if ($offset % 3 === 0) {
                MealPlanEntry::create([
                    'planned_on' => $day->toDateString(),
                    'kind' => MealKind::Lunch,
                    'title' => $dishes[($offset + 4) % count($dishes)],
                ]);
            }
        }
    }
}
