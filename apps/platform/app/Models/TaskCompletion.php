<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use App\Models\Concerns\HasZonedTimestamps;
use Database\Factories\TaskCompletionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A completed occurrence of a task.
 *
 * Routines have at most one completion per occurrence; the constraint sits in
 * the database, not in code. Without it, ticking on two devices at once would
 * award points twice.
 */
final class TaskCompletion extends Model
{
    /** @use HasFactory<TaskCompletionFactory> */
    use BelongsToHousehold, HasFactory, HasZonedTimestamps;

    protected $fillable = [
        'household_id', 'task_id', 'person_id', 'for_person_id', 'daypart', 'occurred_on', 'completed_at', 'skipped',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_on' => 'immutable_date',
            'completed_at' => 'immutable_datetime',
            'skipped' => 'boolean',
        ];
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        // The history keeps naming a deleted task.
        return $this->belongsTo(Task::class)->withTrashed();
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * The stars booked for it; none without a person or for a task without stars.
     *
     * @return HasOne<PointEntry, $this>
     */
    public function pointEntry(): HasOne
    {
        return $this->hasOne(PointEntry::class);
    }
}
