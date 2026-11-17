<?php

namespace App\Support;

use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The "system locked" screen shown to whoever hammers the sign-in forms. It is triggered by
 * the rate limiters or by a username that carries an attack payload, and it records the
 * address in the log so the on-screen "your IP was recorded" line is true.
 */
class Lockdown
{
    /** How long an address stays locked out after sending an attack payload. */
    public const PAYLOAD_SECONDS = 600;

    /**
     * Quotes, statement separators, markup, comment markers, path tricks and the favourite
     * SQL probes. Real usernames are plain lowercase words, so none of this appears by accident.
     */
    public static function looksLikeAttack(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return strlen($value) > 100
            || preg_match('/[\'"`;<>\\\\\x00]|--|\/\*|\$\{|\{\{|\.\.\/|\bunion\b|\bselect\b|\bor\s+1\s*=\s*1/i', $value) === 1;
    }

    /**
     * Seconds left on a lock set earlier for this address, or 0.
     */
    public static function remaining(Request $request): int
    {
        $until = Cache::get(self::key($request));

        return is_int($until) ? max(0, $until - time()) : 0;
    }

    /**
     * Locks the address for a while and logs why.
     */
    public static function trigger(Request $request, string $reason, int $seconds): void
    {
        Cache::put(self::key($request), time() + $seconds, $seconds);

        self::log($request, $reason);
    }

    /**
     * The answer for a locked address: JSON for the sign-in script, a full page otherwise.
     */
    public static function response(Request $request, int $seconds, string $reason): Response
    {
        $seconds = max(1, $seconds);

        if ($request->expectsJson()) {
            return response()->json([
                'locked' => true,
                'retry_after' => $seconds,
                'reason' => $reason,
                'ip' => $request->ip(),
                'trace' => self::trace($request),
            ], 429)->header('Retry-After', (string) $seconds);
        }

        // Without scripts (or on a reload) the sign-in page itself comes back already locked.
        $admin = $request->is('superadmin*');

        return response()->view('staff.auth.login', [
            'portal' => $admin ? 'admin' : 'staff',
            'attemptRoute' => $admin ? 'admin.login.attempt' : 'staff.login.attempt',
            'quickLoginEnabled' => false,
            'quickLoginUsername' => null,
            'captchaEnabled' => LoginCaptcha::enabled(),
            'locked' => true,
            'lockSeconds' => $seconds,
            'trace' => self::trace($request),
            // A reload has no username in it, so the name typed when the lock began is kept with it.
            'typedUsername' => $request->input('username') ?: Cache::get(self::nameKey($request)),
        ], 429)->header('Retry-After', (string) $seconds);
    }

    /**
     * The name the locked page greets the caller with: the username they typed, or else
     * their system ("Windows user"). Browsers never reveal the computer or profile name.
     *
     * @param  array<string, mixed>|null  $trace
     */
    public static function greeting(mixed $typed, ?array $trace): string
    {
        $typed = is_string($typed) ? trim(preg_replace('/\s+/', ' ', $typed)) : '';

        if ($typed !== '') {
            return Str::limit($typed, 22, '...');
        }

        $os = is_string($trace['os'] ?? null) ? trim(preg_replace('/\s*[\d.\/]+$/', '', $trace['os'])) : '';

        return $os !== '' && ! str_starts_with($os, 'Unknown') ? $os.' user' : 'stranger';
    }

    /**
     * What a rate limiter returns when its limit is hit.
     *
     * @param  array<string, mixed>  $headers
     */
    public static function throttled(Request $request, array $headers): Response
    {
        $seconds = max(1, (int) ($headers['Retry-After'] ?? 60));

        // Kept as a lock on the address too, so a reload (a plain GET the limiter never sees)
        // comes back locked instead of showing the normal sign-in form.
        Cache::put(self::key($request), time() + $seconds, $seconds);
        self::log($request, 'throttle');

        return self::response($request, $seconds, 'throttle');
    }

    private static function key(Request $request): string
    {
        return 'lockdown:'.$request->ip();
    }

    private static function nameKey(Request $request): string
    {
        return 'lockdown-name:'.$request->ip();
    }

    /**
     * What the locked screen shows the caller about themselves: address, browser, system,
     * device and an approximate place.
     *
     * @return array{ip: ?string, browser: string, os: string, device: string, country: ?string, country_code: ?string, city: ?string, isp: ?string}
     */
    public static function trace(Request $request): array
    {
        return ['ip' => $request->ip()]
            + UserAgent::parse($request->userAgent())
            + GeoIp::lookup($request->ip());
    }

    /**
     * A sign-in attempt from an address that is already locked: counted on its latest event.
     */
    public static function recordRepeat(Request $request): void
    {
        if (! $request->isMethod('POST')) {
            return;
        }

        try {
            SecurityEvent::where('ip', $request->ip())->latest('id')->first()?->increment('hits', 1, ['last_seen_at' => now()]);
        } catch (Throwable) {
            // Recording must never stand between the caller and the locked page.
        }
    }

    private static function log(Request $request, string $reason): void
    {
        $username = is_string($request->input('username')) ? Str::limit($request->input('username'), 37) : null;

        if ($username !== null && $username !== '') {
            Cache::put(self::nameKey($request), $username, self::PAYLOAD_SECONDS);
        }

        Log::warning('security.lockdown', [
            'reason' => $reason,
            'ip' => $request->ip(),
            'path' => $request->path(),
            'user_agent' => Str::limit((string) $request->userAgent(), 120),
            'username' => $username,
        ]);

        try {
            // A flood from one address within a minute stays one row with a hit counter.
            $recent = SecurityEvent::where('ip', $request->ip())
                ->where('reason', $reason)
                ->where('last_seen_at', '>=', now()->subMinute())
                ->latest('id')
                ->first();

            if ($recent) {
                $recent->forceFill(['hits' => $recent->hits + 1, 'last_seen_at' => now()])->save();

                return;
            }

            $trace = self::trace($request);

            SecurityEvent::create([
                'reason' => $reason,
                'ip' => (string) $request->ip(),
                'username' => $username,
                'path' => Str::limit($request->path(), 117),
                'user_agent' => Str::limit((string) $request->userAgent(), 252),
                'browser' => $trace['browser'],
                'os' => $trace['os'],
                'device' => $trace['device'],
                'country' => $trace['country'],
                'country_code' => $trace['country_code'],
                'city' => $trace['city'],
                'isp' => $trace['isp'],
                'last_seen_at' => now(),
            ]);

            // Now and then, drop events older than the retention window.
            if (random_int(1, 50) === 1) {
                SecurityEvent::where('created_at', '<', now()->subDays(max(1, (int) config('services.security_events.keep_days'))))->delete();
            }
        } catch (Throwable $exception) {
            Log::error('security.lockdown.record_failed', ['message' => $exception->getMessage()]);
        }
    }
}
