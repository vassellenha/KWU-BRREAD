<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The /admin honeypot. The superadmin sign-in lives only at /superadmin, so a guest who goes
 * looking for an admin panel at the usual addresses meets the trap: a horror intro, then the "Kewer" scene.
 * The hold is kept per address in the cache (its own keys, apart from the wrong-password
 * ladder in LoginPrank), so a refresh, a new tab or another address of the shop keeps counting down.
 */
class HoneypotTrap
{
    /** Addresses that count as the honeypot, each with its sub-paths. */
    public const PATHS = ['admin', 'administrator', 'wp-admin', 'login', 'dashboard', 'panel', 'cpanel', 'phpmyadmin', 'backend'];

    /** Names of the optional real sound files in public/audio/honeypot/ (any of mp3, ogg, wav, m4a). */
    public const SAMPLES = [
        'door-creak', 'footstep-wet', 'drip', 'heavy-thud', 'cape-rustle', 'fluorescent-buzz',
        'gunshot', 'glass-crack', 'zap', 'crt-off', 'stinger',
    ];

    /** Where a held visitor is sent back to from any other page. */
    public const HOME_PATH = '/admin';

    /** After a hold ends the same address is left alone for this long (seconds). */
    private const GRACE = 120;

    public static function enabled(): bool
    {
        return (bool) config('auth.honeypot_trap');
    }

    /** Phase A, the horror intro, in seconds. */
    public static function horrorSeconds(): int
    {
        return max(1, (int) config('auth.honeypot_horror_seconds', 30));
    }

    /** Phase B, the Kewer scene, in seconds. */
    public static function kewerSeconds(): int
    {
        return max(5, (int) config('auth.honeypot_kewer_seconds', 30));
    }

    /** The whole hold. */
    public static function total(): int
    {
        return self::horrorSeconds() + self::kewerSeconds();
    }

    public static function isTrapPath(Request $request): bool
    {
        return $request->is(...array_merge(self::PATHS, array_map(fn (string $path) => $path.'/*', self::PATHS)));
    }

    /**
     * Seconds since this address's hold began, or null when no hold is running. The server's start
     * timestamp is the only clock: a refresh or a new tab resumes at the right point and never restarts.
     */
    public static function elapsed(Request $request): ?int
    {
        if (! self::enabled()) {
            return null;
        }

        $start = Cache::get(self::key('hold', $request));

        if (! is_int($start)) {
            return null;
        }

        $elapsed = max(0, time() - $start);

        return $elapsed < self::total() ? $elapsed : null;
    }

    /**
     * Seconds left on this address's hold, or 0.
     */
    public static function remaining(Request $request): int
    {
        $elapsed = self::elapsed($request);

        return $elapsed === null ? 0 : self::total() - $elapsed;
    }

    /**
     * "horror" while the intro runs, "kewer" after it.
     */
    public static function phase(int $elapsed): string
    {
        return $elapsed < self::horrorSeconds() ? 'horror' : 'kewer';
    }

    /**
     * "?replay=1" lets a developer run the trap again at once, ignoring the grace period. It exists
     * only outside production, so a visitor of the live site can never use it to restart or dodge a hold.
     */
    public static function replayAllowed(Request $request): bool
    {
        return $request->boolean('replay') && ! app()->environment('production');
    }

    /**
     * The real sound files that are actually present, as name => public URL. The page hands this list to the
     * browser so it never has to probe for a missing file (a probe would be sent to the trap while a hold runs).
     *
     * @return array<string, string>
     */
    public static function samples(?string $directory = null): array
    {
        $directory ??= public_path('audio/honeypot');
        $found = [];

        foreach (self::SAMPLES as $name) {
            foreach (['mp3', 'ogg', 'wav', 'm4a'] as $extension) {
                if (is_file($directory.'/'.$name.'.'.$extension)) {
                    $found[$name] = '/audio/honeypot/'.$name.'.'.$extension;

                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Starts the hold unless the address was just released. False means "leave them alone".
     * The grace period starts with the hold and outlasts it, so Back cannot restart the trap.
     * With $replay the grace period and any finished hold are wiped first (see replayAllowed).
     */
    public static function begin(Request $request, bool $replay = false): bool
    {
        if (! self::enabled()) {
            return false;
        }

        if ($replay) {
            Cache::forget(self::key('hold', $request));
            Cache::forget(self::key('grace', $request));
        } elseif (Cache::has(self::key('grace', $request))) {
            return false;
        }

        // Cache::add keeps a second tab from restarting a hold that is already counting down.
        if (Cache::add(self::key('hold', $request), time(), self::total() + 5)) {
            Cache::put(self::key('grace', $request), true, self::total() + self::GRACE);
            Lockdown::log($request, 'honeypot');
        }

        return true;
    }

    private static function key(string $name, Request $request): string
    {
        return 'honeypot-'.$name.':'.$request->ip();
    }
}
