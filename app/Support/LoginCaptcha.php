<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * A distorted-text image for the staff sign-in forms. The code lives in the session, never in
 * the page, and the first attempt uses it up so a solved image cannot be replayed.
 */
class LoginCaptcha
{
    private const KEY = 'login_captcha_answer';

    /** No I, L, O, 0 or 1, which people confuse in a bent font. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const LENGTH = 5;

    /** How many recently drawn codes are accepted. */
    private const KEEP = 3;

    public static function enabled(): bool
    {
        return (bool) config('auth.login_captcha');
    }

    /**
     * Draws a new code, keeps it in the session and returns the PNG bytes.
     */
    public static function image(Request $request): string
    {
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        // The last few codes stay valid, so a second tab or a quick double refresh does not leave the
        // image on screen without its answer. The first attempt still throws every one of them away.
        $recent = (array) $request->session()->get(self::KEY, []);
        $request->session()->put(self::KEY, array_slice([...$recent, $code], -self::KEEP));

        return self::render($code, resource_path('fonts/DejaVuSans-Bold.ttf'));
    }

    /**
     * Checks the typed text against the recent codes, ignoring case and spaces, and discards them all either way.
     */
    public static function passes(Request $request, mixed $answer): bool
    {
        $expected = (array) $request->session()->pull(self::KEY, []);

        if (! is_string($answer)) {
            return false;
        }

        $typed = strtoupper(preg_replace('/\s+/', '', $answer));
        $matched = false;
        foreach ($expected as $code) {
            $matched = (is_string($code) && hash_equals($code, $typed)) || $matched;
        }

        return $matched;
    }

    /**
     * Draws the letters with the bundled TrueType font. A server whose GD has no FreeType support, or
     * whose copy of the font is missing, falls back to GD's built-in bitmap letters scaled up, so the
     * sign-in form never ends up with a broken image.
     */
    private static function render(string $code, string $font): string
    {
        $width = 300;
        $height = 84;
        $useFont = function_exists('imagettftext') && is_readable($font);

        $text = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($text, 255, 255, 255);
        imagefill($text, 0, 0, $white);

        // Faint scribbles behind the letters.
        $faint = imagecolorallocate($text, 205, 205, 205);
        imagesetthickness($text, 2);
        for ($i = 0; $i < 6; $i++) {
            imageline($text, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $faint);
        }

        // Every letter sits centred in its own equal slot, so the gaps stay even whatever the tilt.
        $ink = imagecolorallocate($text, 24, 24, 28);
        $slot = $width / self::LENGTH;
        foreach (str_split($code) as $i => $letter) {
            if (! $useFont) {
                self::drawBitmapLetter($text, $letter, $slot * $i, $slot, $height);

                continue;
            }

            $size = 32;
            $angle = random_int(-12, 12);
            $box = imagettfbbox($size, $angle, $font, $letter);
            $glyphWidth = max($box[0], $box[2], $box[4], $box[6]) - min($box[0], $box[2], $box[4], $box[6]);
            $glyphTop = min($box[1], $box[3], $box[5], $box[7]);
            $glyphBottom = max($box[1], $box[3], $box[5], $box[7]);
            $x = (int) round($slot * $i + ($slot - $glyphWidth) / 2 - min($box[0], $box[2], $box[4], $box[6]));
            $y = (int) round(($height - ($glyphBottom - $glyphTop)) / 2 - $glyphTop) + random_int(-3, 3);
            imagettftext($text, $size, $angle, $x, $y, $ink, $font, $letter);
        }

        // A sine wave pushes every pixel column up or down, bending the whole word.
        $bent = imagecreatetruecolor($width, $height);
        imagefill($bent, 0, 0, imagecolorallocate($bent, 255, 255, 255));
        $phase = random_int(0, 628) / 100;
        $period = random_int(14, 20);
        for ($column = 0; $column < $width; $column++) {
            $shift = (int) round(sin($column / $period + $phase) * 3);
            imagecopy($bent, $text, $column, $shift, $column, 0, 1, $height);
        }

        // Dark strokes across the letters so cleaning the background is not enough.
        $stroke = imagecolorallocate($bent, 70, 70, 76);
        imagesetthickness($bent, 2);
        for ($i = 0; $i < 3; $i++) {
            imageline($bent, 0, random_int(16, 66), $width, random_int(16, 66), $stroke);
        }
        for ($i = 0; $i < 50; $i++) {
            imagesetpixel($bent, random_int(0, $width - 1), random_int(0, $height - 1), $stroke);
        }

        ob_start();
        imagepng($bent);
        $png = (string) ob_get_clean();

        return $png;
    }

    /**
     * One letter from GD's built-in 9x15 font, scaled to about the size of the TrueType letters and tilted.
     */
    private static function drawBitmapLetter(\GdImage $canvas, string $letter, float $slotLeft, float $slotWidth, int $height): void
    {
        $glyph = imagecreatetruecolor(9, 15);
        imagefill($glyph, 0, 0, imagecolorallocate($glyph, 255, 255, 255));
        imagechar($glyph, 5, 0, 0, $letter, imagecolorallocate($glyph, 24, 24, 28));

        $big = imagecreatetruecolor(36, 60);
        imagecopyresampled($big, $glyph, 0, 0, 0, 0, 36, 60, 9, 15);

        if (function_exists('imagerotate')) {
            $big = imagerotate($big, random_int(-12, 12), imagecolorallocate($big, 255, 255, 255)) ?: $big;
        }

        $glyphWidth = imagesx($big);
        $glyphHeight = imagesy($big);
        imagecopy(
            $canvas,
            $big,
            (int) round($slotLeft + ($slotWidth - $glyphWidth) / 2),
            (int) round(($height - $glyphHeight) / 2) + random_int(-3, 3),
            0,
            0,
            $glyphWidth,
            $glyphHeight,
        );
    }
}
