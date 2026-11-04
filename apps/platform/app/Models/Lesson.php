<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LessonCadence;
use App\Models\Concerns\BelongsToHousehold;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A recurring lesson.
 *
 * Its time is either a period number in the child's bell schedule or a free
 * time on this row — never both. A database check constraint enforces that,
 * so the question of which one wins never comes up.
 */
final class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use BelongsToHousehold, HasFactory;

    protected $fillable = [
        'household_id', 'person_id', 'title', 'weekday', 'period_number',
        'starts_at', 'ends_at', 'interval', 'cadence', 'anchor_on', 'active', 'color',
    ];

    /*
     * Eloquent does not read database defaults back. Without these lines a
     * freshly created lesson would have no cadence.
     */
    protected $attributes = [
        'interval' => 1,
        'cadence' => LessonCadence::Calendar->value,
        'active' => true,
    ];

    /*
     * The colour belongs to the child's subject, not to one slot: a change
     * reaches every lesson of the same title. A new lesson without a colour
     * leaves the others alone.
     */
    protected static function booted(): void
    {
        self::saved(function (Lesson $lesson): void {
            if ($lesson->wasRecentlyCreated ? $lesson->color === null : ! $lesson->wasChanged('color')) {
                return;
            }

            self::query()
                ->where('person_id', $lesson->person_id)
                ->where('title', $lesson->title)
                ->whereKeyNot($lesson->getKey())
                ->update(['color' => $lesson->color]);
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'period_number' => 'integer',
            'interval' => 'integer',
            'cadence' => LessonCadence::class,
            'anchor_on' => 'immutable_date',
            'active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return HasMany<LessonChange, $this> */
    public function changes(): HasMany
    {
        return $this->hasMany(LessonChange::class);
    }

    /** Whether the time comes from the bell schedule instead of this row. */
    public function usesPeriod(): bool
    {
        return $this->period_number !== null;
    }
}
