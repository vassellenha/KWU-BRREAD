<?php

namespace App\Support;

/**
 * Draws a Code 39 barcode as an inline SVG, so the printed receipt can carry a scannable
 * order number without an image library or a network call.
 *
 * Code 39 only knows 0-9, A-Z, and "- . space". Anything else in the text is dropped
 * before encoding, which keeps order numbers like BRREAD-AB12CD34 intact.
 */
class Code39
{
    /** Bar/space widths per character: n = narrow, w = wide; bar, space, bar, space ... */
    private const PATTERNS = [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn', 'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw',
        'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn',
        'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww',
        'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn',
        'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn', 'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw',
        'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '*' => 'nwnnwnwnn',
    ];

    private const WIDE = 3;

    /** The characters a barcode can actually carry, in the order they were given. */
    public static function clean(string $text): string
    {
        return preg_replace('/[^0-9A-Z\-. ]/', '', strtoupper($text)) ?? '';
    }

    /**
     * Bar widths (in modules) for the whole code, bar first, alternating bar/space,
     * including the * start and stop characters and a one module gap between characters.
     *
     * @return list<int>
     */
    public static function modules(string $text): array
    {
        $framed = '*'.self::clean($text).'*';
        $modules = [];

        foreach (str_split($framed) as $index => $char) {
            if ($index > 0) {
                $modules[] = 1; // inter-character gap (a space, so it lands after a bar)
            }
            foreach (str_split(self::PATTERNS[$char]) as $width) {
                $modules[] = $width === 'w' ? self::WIDE : 1;
            }
        }

        return $modules;
    }

    public static function svg(string $text, int $height = 56, string $color = '#201b15'): string
    {
        $modules = self::modules($text);
        $total = array_sum($modules);
        $x = 0;
        $bars = '';

        foreach ($modules as $i => $width) {
            // Even positions are bars and odd ones spaces, except the inter-character gap
            // which is inserted as an odd (space) entry and keeps the alternation intact.
            if ($i % 2 === 0) {
                $bars .= sprintf('<rect x="%d" y="0" width="%d" height="%d"/>', $x, $width, $height);
            }
            $x += $width;
        }

        return sprintf(
            '<svg class="os-barcode" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" preserveAspectRatio="none" '
            .'role="img" aria-label="Barcode %s" shape-rendering="crispEdges" fill="%s">%s</svg>',
            $total,
            $height,
            e(self::clean($text)),
            e($color),
            $bars,
        );
    }
}
