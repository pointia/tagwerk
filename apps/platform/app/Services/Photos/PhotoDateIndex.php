<?php

declare(strict_types=1);

namespace App\Services\Photos;

use App\Models\PhotoDate;
use App\Models\ProviderAccount;

/**
 * Remembers when the pictures of a folder were taken.
 *
 * Reading goes in portions: a freshly connected archive of thousands of
 * pictures would otherwise hold up the wall's first request for seconds.
 * Pictures not read yet simply have no date for now; after a few requests
 * the folder is complete.
 */
final class PhotoDateIndex
{
    /** Pictures read per request. */
    public const BATCH = 300;

    /**
     * The date of each path, read where it is missing.
     *
     * @param  array<int, string>  $paths  relative to $root
     * @param  bool  $complete  whether $paths is the whole folder; only then
     *                          are rows of vanished pictures removed
     * @return array<string, string|null> path => ISO date
     */
    public function dates(ProviderAccount $account, string $root, array $paths, bool $complete): array
    {
        $known = PhotoDate::query()
            ->where('provider_account_id', $account->id)
            ->get(['path', 'taken_on'])
            ->mapWithKeys(static fn (PhotoDate $row): array => [$row->path => $row->taken_on?->toDateString()])
            ->all();

        $new = [];

        foreach (array_slice(array_values(array_diff($paths, array_keys($known))), 0, self::BATCH) as $path) {
            $known[$path] = TakenOn::read($root.DIRECTORY_SEPARATOR.$path);
            $new[] = [
                'household_id' => $account->household_id,
                'provider_account_id' => $account->id,
                'path' => $path,
                'taken_on' => $known[$path],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($new !== []) {
            PhotoDate::query()->insertOrIgnore($new);
        }

        if ($complete) {
            $gone = array_diff(array_keys($known), $paths);

            if ($gone !== []) {
                PhotoDate::query()
                    ->where('provider_account_id', $account->id)
                    ->whereIn('path', $gone)
                    ->delete();
            }
        }

        return array_intersect_key($known, array_flip($paths));
    }
}
