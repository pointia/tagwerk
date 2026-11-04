<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns an uploaded photo into a clean, small profile picture.
 *
 * What gets stored is never the uploaded file but a freshly drawn image. That
 * is the actual protection: redrawing leaves nothing but pixels. EXIF data
 * with GPS coordinates, code appended in a polyglot file or scripts fall away
 * without having to recognise them one by one. The in-browser downscaling
 * Filament brings along is mere convenience — a direct request bypasses it.
 */
final class AvatarImage
{
    /** Edge length of the stored image; enough for retina displays too. */
    public const EDGE = 512;

    public function __construct(private readonly ImageDecoder $decoder) {}

    /** Stores the redrawn image and returns its path on the public disk. */
    public function store(string $content): string
    {
        $path = 'avatars/'.Str::lower((string) Str::ulid()).'.webp';

        Storage::disk('public')->put($path, $this->normalize($content));

        return $path;
    }

    /** @return string WebP data, square, at most EDGE pixels. */
    public function normalize(string $content): string
    {
        $image = $this->decoder->decode($content);

        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);

        // Never upscale: a small image would only get bigger, not sharper.
        $target = min(self::EDGE, $side);

        $canvas = imagecreatetruecolor($target, $target);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        // Crop a centred square, as the round frame shows it.
        imagecopyresampled(
            $canvas, $image,
            0, 0,
            intdiv($width - $side, 2), intdiv($height - $side, 2),
            $target, $target,
            $side, $side,
        );

        ob_start();
        imagewebp($canvas, null, 82);

        return (string) ob_get_clean();
    }
}
