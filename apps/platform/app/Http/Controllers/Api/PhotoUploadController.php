<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidImage;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Services\PhotoImage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Photos for the frame, from the phone.
 *
 * The phone shrinks each picture before sending it, to spare the data plan;
 * the server still redraws it (PhotoImage), because only then is EXIF with
 * the location really gone. The fingerprint of the original lets the phone
 * pick the same folder again and send only what is new.
 */
final class PhotoUploadController extends Controller
{
    public function __construct(private readonly PhotoImage $images) {}

    /**
     * Which of these originals the household already has — the trash
     * included: picking the folder again must not bring back what someone
     * just deleted.
     */
    public function known(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fingerprints' => ['required', 'array', 'max:500'],
            'fingerprints.*' => ['string', 'regex:/^[0-9a-f]{64}$/'],
        ]);

        return response()->json([
            'known' => Photo::withTrashed()
                ->whereIn('fingerprint', $data['fingerprints'])
                ->pluck('fingerprint')
                ->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            // The phone sends JPEG; the others for pictures it could not redraw.
            'photo' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:20480'],
            'fingerprint' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/'],
            'taken_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $existing = Photo::withTrashed()->where('fingerprint', $data['fingerprint'])->first();

        if ($existing !== null) {
            return response()->json(['id' => $existing->id, 'duplicate' => true]);
        }

        try {
            $path = $this->images->store((string) $request->file('photo')?->get());
        } catch (InvalidImage $error) {
            throw ValidationException::withMessages(['photo' => $error->getMessage()]);
        }

        try {
            $photo = Photo::create([
                'path' => $path,
                'taken_on' => $data['taken_on'] ?? null,
                'fingerprint' => $data['fingerprint'],
                'user_id' => $request->user()?->getKey(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two phones sent the same picture at the same moment.
            Storage::disk('local')->delete($path);

            return response()->json([
                'id' => Photo::withTrashed()->where('fingerprint', $data['fingerprint'])->value('id'),
                'duplicate' => true,
            ]);
        }

        return response()->json(['id' => $photo->id, 'duplicate' => false], 201);
    }
}
