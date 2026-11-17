<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The joke a person meets when they guess passwords by hand: the first three wrong passwords
 * each pop up a cheeky terminal (the third reads out their IP, city and provider), the fourth
 * plays the "Kewer Kewer" video and pauses sign-in for that address. Each pause is longer than the last (1, 3, then 5 minutes at most). Scripts that
 * keep hammering still hit the rate limits and the lockdown screen; this is for curious humans.
 */
class LoginPrank
{
    /** Wrong passwords before the pause. */
    public const STRIKE_AT = 4;

    /** Pause lengths in seconds, for the first, second and every later pause. */
    public const PAUSES = [60, 180, 300];

    /** How long wrong passwords and pauses are remembered for an address. */
    private const MISS_MEMORY = 1800;

    private const STRIKE_MEMORY = 86400;

    public static function enabled(): bool
    {
        return (bool) config('auth.login_prank');
    }

    /**
     * Seconds left on this address's pause, or 0.
     */
    public static function remaining(Request $request): int
    {
        if (! self::enabled()) {
            return 0;
        }

        $until = Cache::get(self::key('pause', $request));

        return is_int($until) ? max(0, $until - time()) : 0;
    }

    /**
     * Counts a wrong password and says which scene to play.
     *
     * @return array{stage: int, ip: ?string, city: ?string, country: ?string, isp: ?string, retry_after?: int}
     */
    public static function miss(Request $request): array
    {
        $misses = (int) Cache::get(self::key('misses', $request), 0) + 1;
        $place = GeoIp::lookup($request->ip());
        $scene = [
            'stage' => min($misses, self::STRIKE_AT),
            'ip' => $request->ip(),
            'city' => $place['city'],
            'country' => $place['country'],
            'isp' => $place['isp'],
        ];

        if ($misses < self::STRIKE_AT) {
            Cache::put(self::key('misses', $request), $misses, self::MISS_MEMORY);

            return $scene;
        }

        $strikes = (int) Cache::get(self::key('strikes', $request), 0) + 1;
        $seconds = self::PAUSES[min($strikes, count(self::PAUSES)) - 1];

        Cache::put(self::key('strikes', $request), $strikes, self::STRIKE_MEMORY);
        Cache::put(self::key('pause', $request), time() + $seconds, $seconds);
        Cache::forget(self::key('misses', $request));

        return $scene + ['retry_after' => $seconds];
    }

    /**
     * A correct sign-in wipes the slate for that address.
     */
    public static function clear(Request $request): void
    {
        foreach (['misses', 'strikes', 'pause'] as $kind) {
            Cache::forget(self::key($kind, $request));
        }
    }

    private static function key(string $kind, Request $request): string
    {
        return 'login-prank-'.$kind.':'.$request->ip();
    }
}
