<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Gender;
use App\Enums\PersonRole;
use App\Models\Concerns\BelongsToHousehold;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

final class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use BelongsToHousehold, HasFactory;

    protected $fillable = [
        'household_id', 'name', 'color', 'role', 'birthday', 'gender', 'avatar_path',
        'weekly_goal', 'savings_reward_id', 'permissions', 'position',
    ];

    /**
     * The family's order: children by age, the oldest first, then the adults
     * as the household arranged them, guests last. Wherever people stand side by side —
     * task columns, stars, calendar — they stand in this order.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query
            ->orderByRaw('CASE role WHEN ? THEN 0 WHEN ? THEN 2 ELSE 1 END', [PersonRole::Child->value, PersonRole::Guest->value])
            ->orderByRaw('CASE WHEN role = ? THEN birthday END ASC NULLS LAST', [PersonRole::Child->value])
            ->orderByRaw('position ASC NULLS LAST')
            ->orderBy('name');
    }

    /**
     * Only children have a timetable: school, bell schedule and holidays
     * mean nothing for a parent.
     *
     * @param  Builder<self>  $query
     */
    public function scopeChildren(Builder $query): void
    {
        $query->where('role', PersonRole::Child->value);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => PersonRole::class,
            'gender' => Gender::class,
            'birthday' => 'immutable_date',
            'weekly_goal' => 'integer',
            'position' => 'integer',
            'permissions' => 'array',
        ];
    }

    /**
     * A replaced or orphaned image is deleted. Otherwise old photos of
     * children would stay publicly reachable in storage.
     */
    protected static function booted(): void
    {
        self::updated(function (Person $person): void {
            $previous = $person->getRawOriginal('avatar_path');

            if ($person->wasChanged('avatar_path') && is_string($previous) && $previous !== '') {
                Storage::disk('public')->delete($previous);
            }
        });

        self::deleted(function (Person $person): void {
            if (is_string($person->avatar_path) && $person->avatar_path !== '') {
                Storage::disk('public')->delete($person->avatar_path);
            }
        });
    }

    /**
     * The image URL, or null without an image.
     *
     * The frontends then fall back to the initial in the person's colour — an
     * empty circle would be worse than none.
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path === null
            ? null
            : Storage::disk('public')->url($this->avatar_path);
    }

    /**
     * The picture, or the initial on the person's colour — for the admin's
     * stacked faces, where an empty circle would say nothing.
     */
    public function face(): string
    {
        return $this->avatarUrl() ?? self::initialFace($this->name, (string) $this->color);
    }

    /** The initial on a colour, as a data URI — nothing to fetch from anywhere. */
    public static function initialFace(string $name, string $color): string
    {
        $initial = htmlspecialchars(mb_strtoupper(mb_substr(trim($name), 0, 1)), ENT_XML1);
        $color = htmlspecialchars($color, ENT_XML1);

        return 'data:image/svg+xml;base64,'.base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
            ."<rect width=\"32\" height=\"32\" fill=\"{$color}\"/>"
            .'<text x="16" y="21.5" font-family="sans-serif" font-size="16" font-weight="700" '
            ."fill=\"#fff\" text-anchor=\"middle\">{$initial}</text></svg>"
        );
    }

    /**
     * The reward this person is saving up for.
     *
     * @return BelongsTo<Reward, $this>
     */
    public function savingsReward(): BelongsTo
    {
        return $this->belongsTo(Reward::class, 'savings_reward_id');
    }

    /**
     * The bell schedule of this child's school.
     *
     * @return HasMany<LessonPeriod, $this>
     */
    public function lessonPeriods(): HasMany
    {
        return $this->hasMany(LessonPeriod::class)->orderBy('number');
    }

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /**
     * The id if the person belongs to this household — otherwise null.
     *
     * The household scope does not find a foreign id.
     */
    public static function idInHousehold(?int $id): ?int
    {
        if ($id === null) {
            return null;
        }

        return self::query()->whereKey($id)->value('id');
    }
}
