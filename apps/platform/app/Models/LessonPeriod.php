<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Database\Factories\LessonPeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of the bell schedule: "3rd period, 09:50 to 10:40".
 */
final class LessonPeriod extends Model
{
    /** @use HasFactory<LessonPeriodFactory> */
    use BelongsToHousehold, HasFactory;

    protected $fillable = ['household_id', 'person_id', 'number', 'starts_at', 'ends_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
        ];
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
