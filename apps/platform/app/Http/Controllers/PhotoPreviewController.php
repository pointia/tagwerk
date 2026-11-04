<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A photo preview in the admin panel.
 *
 * The wall fetches its images through the API with its device token; the
 * admin panel has neither. Instead of storing the files publicly, the route
 * lives here — behind the login and behind the household.
 */
final class PhotoPreviewController extends Controller
{
    public function __invoke(Photo $photo): BinaryFileResponse
    {
        $path = Storage::disk('local')->path((string) $photo->path);

        abort_unless(is_file($path), 404);

        return response()->file($path, ['Content-Type' => 'image/webp']);
    }
}
