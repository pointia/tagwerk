<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidImage;
use ErrorException;
use GdImage;

/**
 * Turns uploaded bytes into an image — or rejects them.
 *
 * The check lives here and not in the forms: checking a profile picture and a
 * family photo with different strictness would mean two levels of protection
 * and an open door. Raster formats only, a limit before decoding, and the
 * EXIF rotation baked into the pixels.
 */
final class ImageDecoder
{
    /**
     * Limit before decoding. A tiny file can claim huge dimensions and demand
     * gigabytes of memory when unpacked.
     */
    private const MAX_PIXELS = 40_000_000;

    /** Raster formats only. SVG can contain scripts. */
    private const TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    /** @throws InvalidImage */
    public function decode(string $content): GdImage
    {
        $invalid = new InvalidImage(__('Please upload a photo as JPEG, PNG or WebP.'));

        try {
            $info = getimagesizefromstring($content);
        } catch (ErrorException) {
            throw $invalid;
        }

        if ($info === false || ! in_array($info[2], self::TYPES, true)) {
            throw $invalid;
        }

        if ($info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_PIXELS) {
            throw new InvalidImage(__('The picture is too large. 40 megapixels at most.'));
        }

        try {
            $image = imagecreatefromstring($content);
        } catch (ErrorException) {
            throw $invalid;
        }

        if ($image === false) {
            throw $invalid;
        }

        return $this->upright($image, $content);
    }

    /**
     * Phones often store portrait photos in landscape and record the rotation
     * only in EXIF. Since EXIF is dropped when redrawing, the rotation has to
     * go into the pixels first.
     */
    private function upright(GdImage $image, string $content): GdImage
    {
        if (! str_starts_with($content, "\xFF\xD8")) {
            return $image;
        }

        try {
            $exif = exif_read_data('data://image/jpeg;base64,'.base64_encode($content));
        } catch (ErrorException) {
            // Broken EXIF data is no reason to reject the photo.
            return $image;
        }

        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        // imagerotate rotates counter-clockwise.
        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated === false ? $image : $rotated;
    }
}
