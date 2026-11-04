<?php

declare(strict_types=1);

namespace App\Services\Photos;

use App\Jobs\AnnounceHouseholdUpdateJob;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A picture sent from a phone to the household's walls.
 *
 * Lives in the cache, not in a table: it is a moment, not a fact anyone
 * looks up later. The walls learn of it through the realtime announcement
 * and read it with the agenda; a wall without realtime finds it on its next
 * sync, as long as it has not run out.
 */
final class PhotoCast
{
    public const MINUTES = 10;

    /** @param  array<string, mixed>  $photo  as PhotoLibrary::entry() */
    public function show(int $householdId, array $photo): void
    {
        Cache::put($this->key($householdId), [
            'key' => (string) Str::ulid(),
            'photo' => $photo,
            'until' => CarbonImmutable::now()->addMinutes(self::MINUTES)->toIso8601String(),
        ], now()->addMinutes(self::MINUTES));

        // Not delayed like data changes: someone stands beside the wall waiting.
        AnnounceHouseholdUpdateJob::dispatch($householdId, 'photos');
    }

    /** @return array{key: string, photo: array<string, mixed>, until: string}|null */
    public function current(int $householdId): ?array
    {
        $cast = Cache::get($this->key($householdId));

        return is_array($cast) ? $cast : null;
    }

    private function key(int $householdId): string
    {
        return "photo-cast:{$householdId}";
    }
}
