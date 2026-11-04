<?php

declare(strict_types=1);

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Support\HtmlString;

/**
 * A QR code as inline SVG.
 *
 * Drawn by hand instead of through an image writer: Bacon's writers either
 * need ImageMagick or produce an SVG with a fixed size and its own header.
 * Here the image sits in a Filament modal, grows with it and takes the theme
 * colour — a matrix of squares is all that takes.
 */
final class QrCode
{
    public static function svg(string $content, int $margin = 2): HtmlString
    {
        /*
         * Medium error correction: a quarter of the image may be covered or
         * smudged and the code still reads — enough for a screen without
         * making the pattern needlessly dense.
         */
        $matrix = Encoder::encode($content, ErrorCorrectionLevel::M())->getMatrix();

        $width = $matrix->getWidth();
        $total = $width + 2 * $margin;

        $squares = '';

        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($matrix->get($x, $y) !== 1) {
                    continue;
                }

                $squares .= sprintf(
                    '<rect x="%d" y="%d" width="1" height="1"/>',
                    $x + $margin,
                    $y + $margin,
                );
            }
        }

        // Black on white, fixed: in dark mode currentColor turned the modules
        // white on the white quiet zone, and inverted codes scan poorly.
        return new HtmlString(sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" '
            .'shape-rendering="crispEdges" fill="#000" role="img" '
            .'aria-label="QR-Code">'
            .'<rect width="%1$d" height="%1$d" fill="#fff"/>%2$s</svg>',
            $total,
            $squares,
        ));
    }
}
