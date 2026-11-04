<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventOrigin;
use App\Models\Concerns\BelongsToHousehold;
use App\Models\Concerns\HasZonedTimestamps;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use BelongsToHousehold, HasFactory, HasZonedTimestamps;

    protected $fillable = [
        'household_id', 'calendar_id', 'external_id', 'external_version',
        'recurring_event_id', 'title', 'description', 'location',
        'starts_at', 'ends_at', 'all_day', 'origin', 'revision_marker',
        'external_updated_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'all_day' => 'boolean',
            'origin' => EventOrigin::class,
            'external_updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Calendar, $this> */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    /**
     * Explicit assignment — step 1 of the resolution rule.
     *
     * @return BelongsToMany<Person, $this>
     */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'event_person')->withTimestamps();
    }

    /**
     * Who the event belongs to, following the three-step rule of section 5.2:
     *
     * 1. Explicit assignment on the event → always wins
     * 2. Otherwise the calendar's people → "everything in Apo's calendar is Apo's"
     * 3. Otherwise a family calendar → empty, i.e. neutral display for everyone
     *
     * @return Collection<int, Person>
     */
    public function resolvedPeople(): Collection
    {
        $explicit = $this->people;

        if ($explicit->isNotEmpty()) {
            return $explicit;
        }

        /** @var Collection<int, Person> */
        return $this->calendar?->people ?? new Collection;
    }
}
