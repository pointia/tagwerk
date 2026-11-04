<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An assignee for a single occurrence that deviates from the rotation.
 *
 * Without a row the rule applies. That is the whole difference — which is why
 * there is no "derived or set" field.
 */
final class TaskAssignment extends Model
{
    use BelongsToHousehold;

    protected $fillable = ['household_id', 'task_id', 'person_id', 'occurred_on'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['occurred_on' => 'immutable_date'];
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
