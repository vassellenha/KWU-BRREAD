<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Turns an uploaded review photo into the picture the site keeps: decoded with GD (so whatever was uploaded
 * is never served as it came, metadata and location included), turned upright when the phone says so,
 * scaled to at most MAX_SIDE px on its longest side and re-encoded as WebP (JPEG where GD has no WebP).
 */
class ReviewImage
{
    public const MAX_SIDE = 1280;

    /**
     * @return array{mime: string, width: int, height: int, data: string}
     */
    public static function fromUpload(UploadedFile $file): array
    {
        $bytes = (string) file_get_contents($file->getRealPath());
        $image = @imagecreatefromstring($bytes);
        if (! $image instanceof GdImage) {
            throw new RuntimeException('unreadable image');
        }

        $image = self::upright($image, $file);

        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1, self::MAX_SIDE / max($w, $h));
        if ($scale < 1) {
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $small = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($small, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($image);
            $image = $small;
            [$w, $h] = [$nw, $nh];
        }

        ob_start();
        if (function_exists('imagewebp')) {
            imagewebp($image, null, 80);
            $mime = 'image/webp';
        } else {
            imagejpeg($image, null, 82);
            $mime = 'image/jpeg';
        }
        $encoded = (string) ob_get_clean();
        imagedestroy($image);

        return ['mime' => $mime, 'width' => $w, 'height' => $h, 'data' => base64_encode($encoded)];
    }

    /** Phones store photos sideways and say so in the EXIF orientation; turn the pixels the way it says. */
    private static function upright(GdImage $image, UploadedFile $file): GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }
        $exif = @exif_read_data($file->getRealPath());
        $turn = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $turn ? (imagerotate($image, $turn, 0) ?: $image) : $image;
    }
}
