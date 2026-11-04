<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Albums of uploaded photos, made in the library.
 *
 * Whoever may upload may sort: an album changes no picture, it only names a
 * selection a frame can show. Reading the list is the wall's too
 * (`WallPhotoController::albums`).
 */
final class PhotoAlbumController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $album = PhotoAlbum::query()->create($this->validated($request));

        return response()->json(['id' => $album->id, 'name' => $album->name], 201);
    }

    public function update(Request $request, int $album): JsonResponse
    {
        $album = PhotoAlbum::query()->findOrFail($album);
        $album->update($this->validated($request));

        return response()->json(['id' => $album->id, 'name' => $album->name]);
    }

    /** The album goes, its photos stay. */
    public function destroy(int $album): Response
    {
        $album = PhotoAlbum::query()->findOrFail($album);
        $album->delete();

        return response()->noContent();
    }

    public function add(Request $request, int $album): JsonResponse
    {
        $album = PhotoAlbum::query()->findOrFail($album);
        $album->photos()->syncWithoutDetaching(
            array_fill_keys($this->photoIds($request), ['created_at' => now()]),
        );

        return response()->json(['count' => $album->photos()->count()]);
    }

    public function remove(Request $request, int $album): JsonResponse
    {
        $album = PhotoAlbum::query()->findOrFail($album);
        $album->photos()->detach($this->photoIds($request));

        return response()->json(['count' => $album->photos()->count()]);
    }

    /** @return array{name: string} */
    private function validated(Request $request): array
    {
        /** @var array{name: string} */
        return $request->validate(['name' => ['required', 'string', 'max:80']]);
    }

    /**
     * Only the household's own photos — the scope keeps out an id someone
     * guessed from another family.
     *
     * @return list<int>
     */
    private function photoIds(Request $request): array
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        return Photo::query()->whereIn('id', $data['ids'])->pluck('id')->map(intval(...))->all();
    }
}
