<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LessonChangeKind;
use App\Models\Concerns\BelongsToHousehold;
use Database\Factories\LessonChangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A lesson's deviation on a single day.
 */
final class LessonChange extends Model
{
    /** @use HasFactory<LessonChangeFactory> */
    use BelongsToHousehold, HasFactory;

    protected $fillable = [
        'household_id', 'lesson_id', 'date', 'kind', 'title', 'starts_at', 'ends_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'kind' => LessonChangeKind::class,
        ];
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
