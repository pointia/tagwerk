<?php

declare(strict_types=1);

namespace App\Filament\Resources\Lessons\Schemas;

use App\Enums\LessonCadence;
use App\Models\Lesson;
use App\Models\LessonPeriod;
use App\Models\Person;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/** Without `household_id` — same reasoning as in `TaskForm`. */
class LessonForm
{
    /**
     * The weekdays on which lessons are conceivable.
     *
     * @return array<int, string>
     */
    public static function weekdays(): array
    {
        return [
            1 => __('Monday'),
            2 => __('Tuesday'),
            3 => __('Wednesday'),
            4 => __('Thursday'),
            5 => __('Friday'),
            6 => __('Saturday'),
            7 => __('Sunday'),
        ];
    }

    /**
     * Ready-made subject colours; a free one is possible too.
     *
     * @return array<string, string>
     */
    public static function swatches(): array
    {
        return [
            '#ef4444' => __('Red'),
            '#f97316' => __('Orange'),
            '#eab308' => __('Yellow'),
            '#22c55e' => __('Green'),
            '#14b8a6' => __('Turquoise'),
            '#3b82f6' => __('Blue'),
            '#8b5cf6' => __('Violet'),
            '#ec4899' => __('Pink'),
            '#a16207' => __('Brown'),
            '#64748b' => __('Grey'),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('person_id')
                    ->label(__('Child'))
                    ->options(fn (?Lesson $record): array => Person::query()
                        ->where(fn ($query) => $query->children()->orWhere('id', $record?->person_id))
                        ->ordered()
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    // The bell schedule belongs to the child: when it changes,
                    // the selectable period numbers change.
                    ->live(),

                TextInput::make('title')
                    ->label(__('Subject'))
                    ->required()
                    ->maxLength(255)
                    ->placeholder(__('Maths'))
                    // A subject the child already has brings its colour along.
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                        if (blank($get('color'))) {
                            $set('color', self::subjectColor($get('person_id'), $state));
                        }
                    }),

                ViewField::make('color')
                    ->label(__('Colour'))
                    ->view('filament.forms.color-swatches')
                    ->viewData(['swatches' => self::swatches()])
                    ->rule('nullable')
                    ->rule('regex:/^#[0-9a-fA-F]{6}$/')
                    ->helperText(__('Applies to this child\'s whole subject.'))
                    ->columnSpanFull(),

                // On create from the bell schedule the grid picks the days.
                Select::make('weekday')
                    ->label(__('Weekday'))
                    ->options(self::weekdays())
                    ->default(1)
                    ->visible(fn (Get $get, string $operation): bool => $operation !== 'create' || $get('time_source') === 'custom')
                    ->required(),

                ToggleButtons::make('time_source')
                    ->label(__('Slot'))
                    ->options([
                        'period' => __('From the bell schedule'),
                        'custom' => __('Own time'),
                    ])
                    ->inline()
                    ->live()
                    ->default('period')
                    ->afterStateHydrated(function (?Lesson $record, Set $set): void {
                        if ($record !== null) {
                            $set('time_source', $record->usesPeriod() ? 'period' : 'custom');
                        }
                    })
                    ->helperText(fn (Get $get): string => self::periodOptions($get('person_id')) === []
                        ? __('No bell schedule is set for this child yet — it lives on the person.')
                        : ''),

                Select::make('period_number')
                    ->label(__('Period'))
                    ->options(fn (Get $get): array => self::periodOptions($get('person_id')))
                    ->visible(fn (Get $get, string $operation): bool => $operation !== 'create' && $get('time_source') !== 'custom')
                    ->required(fn (Get $get): bool => $get('time_source') !== 'custom'),

                /*
                 * The whole week at once: every slot the subject takes is one
                 * tap, instead of one form per lesson. Taken slots show their
                 * subject and cannot be chosen.
                 */
                ViewField::make('slots')
                    ->label(__('Lessons in the weekly grid'))
                    ->view('filament.forms.lesson-grid')
                    ->viewData(fn (Get $get): array => [
                        'periods' => self::periods($get('person_id')),
                        'taken' => self::takenSlots($get('person_id')),
                        'weekdays' => array_slice(self::weekdays(), 0, 5, true),
                    ])
                    ->default([])
                    ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && $get('time_source') !== 'custom')
                    ->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('time_source') !== 'custom')
                    ->validationMessages(['required' => __('Tap at least one cell in the grid.')])
                    ->columnSpanFull(),

                TimePicker::make('starts_at')
                    ->label(__('From'))
                    ->seconds(false)
                    ->visible(fn (Get $get): bool => $get('time_source') === 'custom')
                    ->required(fn (Get $get): bool => $get('time_source') === 'custom'),

                TimePicker::make('ends_at')
                    ->label(__('To'))
                    ->seconds(false)
                    ->visible(fn (Get $get): bool => $get('time_source') === 'custom')
                    ->required(fn (Get $get): bool => $get('time_source') === 'custom'),

                ToggleButtons::make('interval')
                    ->label(__('How often'))
                    ->options([
                        1 => __('Every week'),
                        2 => __('Every other week'),
                    ])
                    ->inline()
                    ->default(1)
                    ->live()
                    ->required(),

                /*
                 * As radio and not as buttons: the difference between the
                 * two cadences only shows when holidays lie in between —
                 * without the explanation below, the choice would be a
                 * guess.
                 */
                Radio::make('cadence')
                    ->label(__('Rhythm'))
                    ->options(collect(LessonCadence::cases())
                        ->mapWithKeys(fn (LessonCadence $cadence): array => [$cadence->value => $cadence->label()])
                        ->all())
                    ->descriptions(collect(LessonCadence::cases())
                        ->mapWithKeys(fn (LessonCadence $cadence): array => [$cadence->value => $cadence->description()])
                        ->all())
                    ->default(LessonCadence::Calendar->value)
                    ->visible(fn (Get $get): bool => (int) $get('interval') > 1)
                    ->columnSpanFull(),

                DatePicker::make('anchor_on')
                    ->label(__('First on'))
                    ->default(now()->startOfWeek())
                    ->required()
                    ->helperText(fn (Get $get): string => (int) $get('interval') > 1
                        ? __('The two-week rhythm counts from this day.')
                        : __('The lesson applies from this day.')),

                Toggle::make('active')
                    ->label(__('Active'))
                    ->default(true)
                    ->helperText(__('Lessons switched off are kept but appear nowhere.')),
            ]);
    }

    /**
     * Cleans up before saving.
     *
     * The database does not allow a period number and a time at once. Without
     * this step the old value would stay when switching, and the record could
     * no longer be saved.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        $fromPeriods = ($data['time_source'] ?? 'period') !== 'custom';

        unset($data['time_source']);

        if ($fromPeriods) {
            $data['starts_at'] = null;
            $data['ends_at'] = null;
        } else {
            $data['period_number'] = null;
        }

        return $data;
    }

    /**
     * The child's bell schedule, for the grid's rows.
     *
     * @return array<int, array{number: int, time: string}>
     */
    public static function periods(mixed $personId): array
    {
        if (! is_numeric($personId)) {
            return [];
        }

        return LessonPeriod::query()
            ->where('person_id', (int) $personId)
            ->orderBy('number')
            ->get()
            ->map(fn (LessonPeriod $period): array => [
                'number' => $period->number,
                'time' => substr((string) $period->starts_at, 0, 5).'–'.substr((string) $period->ends_at, 0, 5),
            ])
            ->all();
    }

    /** The colour the child's subject already has, if any. */
    public static function subjectColor(mixed $personId, ?string $title): ?string
    {
        if (! is_numeric($personId) || blank($title)) {
            return null;
        }

        return Lesson::query()
            ->where('person_id', (int) $personId)
            ->where('title', trim($title))
            ->whereNotNull('color')
            ->value('color');
    }

    /**
     * Slots already holding an active lesson of the child: "weekday-period" →
     * subject and its colour.
     *
     * @return array<string, array{title: string, color: ?string}>
     */
    public static function takenSlots(mixed $personId): array
    {
        if (! is_numeric($personId)) {
            return [];
        }

        return Lesson::query()
            ->where('person_id', (int) $personId)
            ->where('active', true)
            ->whereNotNull('period_number')
            ->get()
            ->mapWithKeys(fn (Lesson $lesson): array => ["{$lesson->weekday}-{$lesson->period_number}" => [
                'title' => $lesson->title,
                'color' => $lesson->color,
            ]])
            ->all();
    }

    /**
     * The period numbers from the child's bell schedule.
     *
     * @return array<int, string>
     */
    private static function periodOptions(mixed $personId): array
    {
        if (! is_numeric($personId)) {
            return [];
        }

        return LessonPeriod::query()
            ->where('person_id', (int) $personId)
            ->orderBy('number')
            ->get()
            ->mapWithKeys(fn (LessonPeriod $period): array => [
                $period->number => sprintf(
                    __('Lesson %d (%s–%s)'),
                    $period->number,
                    substr((string) $period->starts_at, 0, 5),
                    substr((string) $period->ends_at, 0, 5),
                ),
            ])
            ->all();
    }
}
