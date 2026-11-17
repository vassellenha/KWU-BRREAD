<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * A one-time arithmetic question for the staff sign-in forms. The answer lives in the
 * session, never in the page, and is consumed by the first attempt so a solved question
 * cannot be replayed.
 */
class LoginCaptcha
{
    private const KEY = 'login_captcha_answer';

    public static function enabled(): bool
    {
        return (bool) config('auth.login_captcha');
    }

    /**
     * Draws a fresh question and returns its text.
     */
    public static function issue(Request $request): string
    {
        $a = random_int(2, 9);
        $b = random_int(2, 9);

        if (random_int(0, 1) === 0) {
            $request->session()->put(self::KEY, $a + $b);

            return "{$a} + {$b}";
        }

        [$a, $b] = $a >= $b ? [$a, $b] : [$b, $a];
        $request->session()->put(self::KEY, $a - $b);

        return "{$a} - {$b}";
    }

    /**
     * Checks the typed answer and discards the question either way.
     */
    public static function passes(Request $request, mixed $answer): bool
    {
        $expected = $request->session()->pull(self::KEY);

        return $expected !== null
            && is_string($answer)
            && preg_match('/^-?\d{1,3}$/', trim($answer)) === 1
            && (int) trim($answer) === (int) $expected;
    }
}
