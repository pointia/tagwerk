<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Household;
use App\Models\User;
use App\Notifications\HouseholdArchiveReady;
use App\Services\HouseholdArchive;
use App\Support\OutgoingMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photos can be gigabytes: the archive is built in the queue, and the
 * export page (and a mail) offer it once it is there.
 */
final class BuildHouseholdArchiveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const DAYS = 7;

    public int $timeout = 1800;

    public function __construct(public readonly int $householdId, public readonly int $userId, public readonly bool $secrets = false) {}

    public static function cacheKey(int $householdId): string
    {
        return "household-archive:{$householdId}";
    }

    public function handle(HouseholdArchive $archive): void
    {
        $household = Household::query()->find($this->householdId);

        if ($household === null) {
            return;
        }

        $file = 'exports/'.$household->id.'/'.Str::lower((string) Str::ulid()).'.zip';
        Storage::disk('local')->makeDirectory(dirname($file));
        $archive->write($household, Storage::disk('local')->path($file), $this->secrets);

        Cache::put(self::cacheKey($this->householdId), ['file' => $file, 'at' => now()->toIso8601String(), 'secrets' => $this->secrets], now()->addDays(self::DAYS));

        $user = User::query()->find($this->userId);

        if ($user !== null && OutgoingMail::enabled()) {
            $user->notify(new HouseholdArchiveReady($household, $file, $this->secrets));
        }
    }
}
