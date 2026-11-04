<?php

declare(strict_types=1);

namespace App\Services\Photos;

use Throwable;

/**
 * The day a picture was taken, read from the file itself.
 *
 * First the camera's own record (EXIF `DateTimeOriginal`), then a date in
 * the file name, as phones write it ("IMG_20240512_…", "2024-05-12 18.03").
 * Never the file's modification time: a downloaded album carries the day of
 * the download, and every picture would count as recent.
 */
final class TakenOn
{
    /** Files that can carry EXIF; PNG and friends only have their name. */
    private const EXIF_TYPES = ['jpg', 'jpeg'];

    /** @return string|null ISO date */
    public static function read(string $file): ?string
    {
        return self::fromExif($file) ?? self::fromName(basename($file));
    }

    /** @return string|null ISO date */
    public static function fromName(string $name): ?string
    {
        if (preg_match('/(?<!\d)((?:19|20)\d{2})[-_.]?(\d{2})[-_.]?(\d{2})(?!\d)/', $name, $match) !== 1) {
            return null;
        }

        return self::date((int) $match[1], (int) $match[2], (int) $match[3]);
    }

    private static function fromExif(string $file): ?string
    {
        if (! in_array(mb_strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::EXIF_TYPES, true)) {
            return null;
        }

        /*
         * A broken EXIF block raises a warning, and Laravel turns warnings
         * into exceptions. One odd picture must not stop the whole folder.
         */
        try {
            $exif = exif_read_data($file, 'EXIF');
        } catch (Throwable) {
            return null;
        }

        $stamp = is_array($exif) ? ($exif['DateTimeOriginal'] ?? $exif['DateTime'] ?? null) : null;

        if (! is_string($stamp) || preg_match('/^(\d{4}):(\d{2}):(\d{2})/', $stamp, $match) !== 1) {
            return null;
        }

        return self::date((int) $match[1], (int) $match[2], (int) $match[3]);
    }

    /** Cameras without a set clock write "0000:00:00" — that is no date. */
    private static function date(int $year, int $month, int $day): ?string
    {
        return $year >= 1900 && checkdate($month, $day, $year)
            ? sprintf('%04d-%02d-%02d', $year, $month, $day)
            : null;
    }
}
