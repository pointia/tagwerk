<?php

declare(strict_types=1);

namespace App\Filament\Resources\Lessons\Pages;

use App\Filament\Resources\Lessons\LessonResource;
use App\Filament\Resources\Lessons\Schemas\LessonForm;
use App\Models\Lesson;
use App\Models\Person;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateLesson extends CreateRecord
{
    protected static string $resource = LessonResource::class;

    /**
     * From an empty cell of the timetable grid: child and slot come along
     * in the address. Checked, not trusted — only a child of this household
     * and a slot that is still free.
     */
    protected function afterFill(): void
    {
        $person = Person::query()->children()->find(request()->integer('person'));

        if ($person === null) {
            return;
        }

        $this->data['person_id'] = $person->id;

        $slot = (string) request()->query('slot', '');

        if (preg_match('/^[1-7]-\d{1,2}$/', $slot) === 1 && ! array_key_exists($slot, LessonForm::takenSlots($person->id))) {
            $this->data['slots'] = [$slot];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return LessonForm::normalize($data);
    }

    /**
     * One lesson per chosen slot of the grid; with its own time, just one.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $slots = array_values(array_filter((array) ($data['slots'] ?? []), 'is_string'));
        unset($data['slots']);

        if (($data['starts_at'] ?? null) !== null) {
            return Lesson::create($data);
        }

        // Checked again here: the grid only greys out what it knew of.
        $taken = LessonForm::takenSlots($data['person_id']);
        $clash = array_values(array_intersect($slots, array_keys($taken)));

        if ($clash !== []) {
            throw ValidationException::withMessages([
                'data.slots' => __('Taken in the meantime: :lessons.', ['lessons' => implode(', ', array_map(fn (string $key): string => $taken[$key]['title'], $clash))]),
            ]);
        }

        return DB::transaction(function () use ($slots, $data): Model {
            $lessons = array_map(function (string $slot) use ($data): Lesson {
                [$weekday, $period] = array_map('intval', explode('-', $slot, 2));

                return Lesson::create([...$data, 'weekday' => $weekday, 'period_number' => $period]);
            }, $slots);

            return $lessons[0];
        });
    }

    /**
     * "Create & create another" keeps the child: a timetable is entered one
     * child at a time.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function preserveFormDataWhenCreatingAnother(array $data): array
    {
        return ['person_id' => $data['person_id'] ?? null];
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('Lessons created');
    }
}
