<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Services\Photos\PhotoLibrary;
use App\Services\Photos\PhotoMemories;
use App\Support\HouseholdContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The photo frame.
 *
 * The wall fetches a batch of photos and cycles through them itself instead
 * of asking for every change. The frame keeps running when the home network
 * drops briefly, and the server sees one request instead of 120 an hour.
 */
final class WallPhotoController extends Controller
{
    public function __construct(
        private readonly PhotoLibrary $library,
        private readonly PhotoMemories $memories,
    ) {}

    /**
     * A batch. `from` and `albums` come from the device's preferences or a
     * tile's settings — two frames on one wall may draw differently.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'from' => ['nullable', 'string', 'in:all,favorites,albums'],
            'albums' => ['nullable', 'array', 'max:50'],
            'albums.*' => ['integer'],
        ]);

        return response()->json([
            'photos' => $this->library->random(
                (int) ($data['limit'] ?? 40),
                $data['from'] ?? 'all',
                array_map(intval(...), $data['albums'] ?? []),
            ),
        ]);
    }

    /** Stories from the pictures' dates, newest reasons first. */
    public function memories(): JsonResponse
    {
        $household = Household::query()->find(app(HouseholdContext::class)->id());

        return response()->json([
            'memories' => $this->memories->on($this->library->today($household)),
        ]);
    }

    /**
     * A heart from the wall or the phone. Like a recipe favourite it needs no
     * permission: it changes nothing anyone relies on.
     */
    public function favorite(Request $request, int $photo): JsonResponse
    {
        $data = $request->validate(['favorite' => ['required', 'boolean']]);

        $record = Photo::query()->findOrFail($photo);
        $record->update(['favorite' => $data['favorite']]);

        return response()->json(['favorite' => $record->favorite]);
    }

    /** The household's albums, for choosing what a frame shows. */
    public function albums(): JsonResponse
    {
        return response()->json([
            'albums' => PhotoAlbum::query()
                ->withCount('photos')
                ->orderBy('name')
                ->get()
                ->map(static fn (PhotoAlbum $album): array => [
                    'id' => $album->id,
                    'name' => $album->name,
                    'count' => $album->photos_count,
                    'cover' => ($cover = $album->photos()->latest('photo_album_photo.created_at')->value('photos.id')) !== null
                        ? "/api/wall/photos/builtin/{$cover}"
                        : null,
                ])
                ->all(),
        ]);
    }

    /**
     * The file itself.
     *
     * Only what a source recognises as its own is served — guessed paths lead
     * nowhere, not into the server's file system.
     */
    public function show(string $source, string $photo): BinaryFileResponse
    {
        $file = $this->library->file($source, $photo) ?? abort(404);

        return response()->file($file->path, [
            'Content-Type' => $file->mime,
            // An image never changes under the same id.
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
