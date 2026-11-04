<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Household;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Photo> */
final class PhotoFactory extends Factory
{
    protected $model = Photo::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'path' => 'photos/'.Str::lower((string) Str::ulid()).'.webp',
            'caption' => null,
            'taken_on' => null,
        ];
    }
}
