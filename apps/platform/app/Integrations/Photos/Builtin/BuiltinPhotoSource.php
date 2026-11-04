<?php

declare(strict_types=1);

namespace App\Integrations\Photos\Builtin;

use App\Models\Photo as PhotoRow;
use Illuminate\Support\Facades\Storage;
use Tagwerk\PluginApi\Connections\ConnectedAccount;
use Tagwerk\PluginApi\Photos\ListsPhotos;
use Tagwerk\PluginApi\Photos\Photo;
use Tagwerk\PluginApi\Photos\PhotoFile;
use Tagwerk\PluginApi\Photos\PhotoSource;

/**
 * The photos the household uploaded itself.
 *
 * Always there and without any third-party service — like the built-in
 * recipe book. Whoever never syncs a folder still has a picture frame.
 */
final class BuiltinPhotoSource implements ListsPhotos, PhotoSource
{
    public const KEY = 'builtin';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('Uploaded photos');
    }

    /** Configured as soon as a picture exists: an empty frame is not a source. */
    public function isConfigured(?ConnectedAccount $account): bool
    {
        return PhotoRow::query()->exists();
    }

    /** @return array<int, Photo> */
    public function random(?ConnectedAccount $account, int $count): array
    {
        return PhotoRow::query()
            ->inRandomOrder()
            ->limit($count)
            ->get()
            ->map(self::describe(...))
            ->all();
    }

    /** @return array<int, Photo> */
    public function all(?ConnectedAccount $account): array
    {
        return PhotoRow::query()->get()->map(self::describe(...))->all();
    }

    public static function describe(PhotoRow $row): Photo
    {
        return new Photo(
            id: (string) $row->id,
            caption: $row->caption,
            takenOn: $row->taken_on?->toDateString(),
            favorite: (bool) $row->favorite,
        );
    }

    public function file(?ConnectedAccount $account, string $id): ?PhotoFile
    {
        // The query is limited to the household: a guessed row number does
        // not lead into someone else's living room.
        $row = PhotoRow::query()->find($id);

        if ($row === null) {
            return null;
        }

        $path = Storage::disk('local')->path((string) $row->path);

        return is_file($path) ? new PhotoFile($path, 'image/webp') : null;
    }
}
