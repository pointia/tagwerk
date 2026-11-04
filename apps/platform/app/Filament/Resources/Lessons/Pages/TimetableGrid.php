<?php

declare(strict_types=1);

namespace App\Filament\Resources\Lessons\Pages;

use App\Filament\Resources\Lessons\LessonResource;
use App\Filament\Resources\Lessons\Schemas\LessonForm;
use App\Models\Lesson;
use App\Models\LessonPeriod;
use App\Models\Person;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * The timetable as it looks on paper: one weekly grid per child.
 *
 * The same rows and columns as the grid for creating lessons, so what was
 * tapped there is found again here. The plain list stays one click away.
 */
class TimetableGrid extends Page
{
    protected static string $resource = LessonResource::class;

    protected string $view = 'filament.resources.lessons.timetable-grid';

    /** Empty means every child. */
    #[Url]
    public string $child = '';

    /** Empty, `weekly` or `biweekly`. */
    #[Url]
    public string $rhythm = '';

    #[Url]
    public string $search = '';

    #[Url]
    public bool $inactive = false;

    public function getTitle(): string|Htmlable
    {
        return __('Timetable');
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('list')
                ->label(__('List'))
                ->icon(Heroicon::OutlinedListBullet)
                ->color('gray')
                ->url(LessonResource::getUrl('list')),
            CreateAction::make()
                ->url(LessonResource::getUrl('create')),
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $children = Person::query()->children()->ordered()->get();

        $lessons = Lesson::query()
            ->when(! $this->inactive, fn ($query) => $query->where('active', true))
            ->when($this->rhythm === 'weekly', fn ($query) => $query->where('interval', '<=', 1))
            ->when($this->rhythm === 'biweekly', fn ($query) => $query->where('interval', '>', 1))
            ->when(trim($this->search) !== '', fn ($query) => $query->where('title', 'ilike', '%'.trim($this->search).'%'))
            ->orderBy('weekday')
            ->orderBy('period_number')
            ->orderBy('starts_at')
            ->get()
            ->groupBy('person_id');

        $shown = $this->child === ''
            ? $children
            : $children->where('id', (int) $this->child);

        return [
            'children' => $children,
            // The child filter hides whole grids, not cells: creating stays possible.
            'filtering' => $this->search !== '' || $this->rhythm !== '',
            'timetables' => $shown
                ->map(fn (Person $person): array => $this->timetable($person, $lessons->get($person->id, collect())))
                ->values()
                ->all(),
        ];
    }

    /**
     * One child's grid: periods as rows, weekdays as columns, and the
     * lessons with their own time listed below — those fit no row.
     *
     * @param  Collection<int, Lesson>  $lessons
     * @return array<string, mixed>
     */
    private function timetable(Person $person, Collection $lessons): array
    {
        $periods = LessonPeriod::query()
            ->where('person_id', $person->id)
            ->orderBy('number')
            ->get();

        // Saturday and Sunday only where something is on.
        $weekdays = collect(LessonForm::weekdays())
            ->filter(fn (string $name, int $day): bool => $day <= 5 || $lessons->contains('weekday', $day))
            ->all();

        $cells = [];

        foreach ($lessons->whereNotNull('period_number') as $lesson) {
            // Two alternating subjects may share a slot, hence a list.
            $cells["{$lesson->weekday}-{$lesson->period_number}"][] = $this->entry($lesson);
        }

        // A lesson in a period the bell schedule no longer has would vanish.
        $numbers = $periods->pluck('number')->all();
        $orphans = $lessons->whereNotNull('period_number')->reject(fn (Lesson $lesson): bool => in_array($lesson->period_number, $numbers, true));

        return [
            'person' => $person,
            'weekdays' => $weekdays,
            'periods' => $periods->map(fn (LessonPeriod $period): array => [
                'number' => $period->number,
                'time' => substr((string) $period->starts_at, 0, 5).'–'.substr((string) $period->ends_at, 0, 5),
            ])->all(),
            'cells' => $cells,
            'custom' => $lessons->whereNull('period_number')->merge($orphans)
                ->map(fn (Lesson $lesson): array => [
                    ...$this->entry($lesson),
                    'day' => LessonForm::weekdays()[$lesson->weekday] ?? '—',
                    'time' => $lesson->usesPeriod()
                        ? __('Period :number', ['number' => $lesson->period_number])
                        : substr((string) $lesson->starts_at, 0, 5).'–'.substr((string) $lesson->ends_at, 0, 5),
                ])
                ->values()
                ->all(),
            'empty' => $lessons->isEmpty(),
        ];
    }

    /** @return array{title: string, color: ?string, active: bool, rhythm: ?string, url: string} */
    private function entry(Lesson $lesson): array
    {
        return [
            'title' => $lesson->title,
            'color' => $lesson->color,
            'active' => $lesson->active,
            'rhythm' => $lesson->interval > 1
                ? __('every other week, :cadence', ['cadence' => $lesson->cadence->label()])
                : null,
            'url' => LessonResource::getUrl('edit', ['record' => $lesson]),
        ];
    }

    /** Creating from an empty cell: child and slot are already chosen. */
    public function createUrl(Person $person, int $weekday, int $period): string
    {
        return LessonResource::getUrl('create', ['person' => $person->id, 'slot' => "{$weekday}-{$period}"]);
    }
}
