<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Accepts an uploaded family photo — redrawn and private.
 *
 * Unlike the profile picture the aspect ratio is kept: a holiday picture
 * cropped to a square would be a different picture. It is stored on the
 * private disk, not under `public`: family photos belong to nobody who
 * guesses the address. They are served through the API, which asks for the
 * device.
 */
final class PhotoImage
{
    /** Long edge of the stored image. Enough for a tablet in landscape. */
    public const EDGE = 1920;

    public const DIRECTORY = 'photos';

    /** Long edge of the tiles in the phone's photo grid. */
    public const THUMB_EDGE = 480;

    public function __construct(private readonly ImageDecoder $decoder) {}

    /** @return string path on the private disk. */
    public function store(string $content, string $directory = self::DIRECTORY): string
    {
        $path = $directory.'/'.Str::lower((string) Str::ulid()).'.webp';

        Storage::disk('local')->put($path, $this->normalize($content));

        return $path;
    }

    /**
     * The small copy for the grid, made on first request: the full picture
     * is a few hundred kilobytes, and a grid shows dozens at once.
     *
     * @return string path on the private disk.
     */
    public function thumbnail(string $path): string
    {
        $disk = Storage::disk('local');
        $thumb = self::thumbnailPath($path);

        if (! $disk->exists($thumb)) {
            $disk->put($thumb, $this->normalize((string) $disk->get($path), self::THUMB_EDGE));
        }

        return $thumb;
    }

    public static function thumbnailPath(string $path): string
    {
        return self::DIRECTORY.'/thumbs/'.basename($path);
    }

    /** @return string WebP data, aspect ratio kept, long edge at most `$edge`. */
    public function normalize(string $content, int $edge = self::EDGE): string
    {
        $image = $this->decoder->decode($content);

        $width = imagesx($image);
        $height = imagesy($image);

        // Never upscale: a small image would only get bigger, not sharper.
        $factor = min(1.0, $edge / max($width, $height));

        $targetWidth = max(1, (int) round($width * $factor));
        $targetHeight = max(1, (int) round($height * $factor));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagewebp($canvas, null, 82);

        return (string) ob_get_clean();
    }
}
