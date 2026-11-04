<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Daypart;
use App\Enums\PersonRole;
use App\Integrations\Tasks\Builtin\BuiltinTaskProvider;
use App\Models\Concerns\BelongsToHousehold;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Task or routine — the only difference is the `rrule`.
 */
final class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use BelongsToHousehold, HasFactory, SoftDeletes;

    protected $fillable = [
        'household_id', 'person_id', 'rotation', 'title', 'icon', 'notes', 'due_on',
        'rrule', 'points', 'active', 'position', 'per_person', 'per_person_ids', 'dayparts',
        'skip_holidays',
        'provider', 'task_account_id', 'external_id', 'external_version',
    ];

    /*
     * Eloquent does not read database defaults back on freshly created
     * models; without this entry the origin would arrive empty.
     */
    protected $attributes = ['provider' => BuiltinTaskProvider::KEY];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'due_on' => 'immutable_date',
            'active' => 'boolean',
            'points' => 'integer',
            'rotation' => 'array',
            'per_person' => 'boolean',
            'skip_holidays' => 'boolean',
            'per_person_ids' => 'array',
            'dayparts' => 'array',
        ];
    }

    /**
     * The times of day this task is due, in the order of the day; empty is
     * "any time".
     *
     * @return array<int, Daypart>
     */
    public function dayparts(): array
    {
        $chosen = array_map(strval(...), $this->dayparts ?? []);

        return array_values(array_filter(Daypart::cases(), fn (Daypart $part): bool => in_array($part->value, $chosen, true)));
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<ProviderAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ProviderAccount::class, 'task_account_id');
    }

    /** A task from outside — title and due date belong to the provider. */
    public function isImported(): bool
    {
        return $this->external_id !== null;
    }

    /** @return HasMany<TaskCompletion, $this> */
    public function completions(): HasMany
    {
        return $this->hasMany(TaskCompletion::class);
    }

    /**
     * Removes the task everywhere without losing earned stars.
     *
     * With history it is soft-deleted — the ledger entries hang off the
     * completions, and the database refuses a hard delete anyway. Without
     * history there is nothing to keep.
     */
    public function retire(): void
    {
        if ($this->completions()->exists()) {
            $this->delete();

            return;
        }

        $this->forceDelete();
    }

    /** @return HasMany<TaskAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function isRoutine(): bool
    {
        return $this->rrule !== null && $this->rrule !== '';
    }

    /**
     * Who this task stands for, in the family's order: one person, the
     * rotation in its turn order, the chosen children of a task per person
     * (none chosen = every child), or the whole household for a task that
     * belongs to everyone.
     *
     * @param  Collection<int, Person>  $household  `Person::ordered()`, loaded once by the caller
     * @return Collection<int, Person>
     */
    public function responsible(Collection $household): Collection
    {
        if ($this->per_person) {
            $ids = array_map(intval(...), $this->per_person_ids ?? []);

            return $ids === []
                ? $household->filter(fn (Person $person): bool => $person->role === PersonRole::Child)->values()
                : $household->whereIn('id', $ids)->values();
        }

        $rotation = $this->rotationIds();

        if ($rotation !== []) {
            return collect($rotation)
                ->map(fn (int $id): ?Person => $household->firstWhere('id', $id))
                ->filter()
                ->values();
        }

        if ($this->person_id !== null) {
            return $household->where('id', $this->person_id)->values();
        }

        return $household;
    }

    /** @return array<int, int> */
    public function rotationIds(): array
    {
        return array_values(array_filter(
            array_map(intval(...), $this->rotation ?? []),
        ));
    }
}
