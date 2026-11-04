<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Carbon\CarbonImmutable;
use Database\Factories\SchoolClosureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A period without school: holidays, bridge day, day off set by the school.
 */
final class SchoolClosure extends Model
{
    /** @use HasFactory<SchoolClosureFactory> */
    use BelongsToHousehold, HasFactory;

    protected $fillable = ['household_id', 'person_id', 'title', 'starts_on', 'ends_on'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Whether this period makes the day school-free for this child.
     *
     * Without a child it applies to everyone — different schools set
     * different days off, which is why an entry can belong to one child.
     */
    public function covers(CarbonImmutable $day, int $personId): bool
    {
        if ($this->person_id !== null && $this->person_id !== $personId) {
            return false;
        }

        return $day->betweenIncluded($this->starts_on, $this->ends_on);
    }
}
