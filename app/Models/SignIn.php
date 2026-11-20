<?php

namespace App\Models;

use App\Support\GeoIp;
use App\Support\UserAgent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Throwable;

/**
 * A successful sign-in to the staff portal or the superadmin panel. Kept as long as the security events
 * (SECURITY_EVENT_DAYS), so the superadmin can see who came in, when and from where.
 */
class SignIn extends Model
{
    protected $fillable = ['user_id', 'username', 'name', 'role', 'portal', 'ip', 'browser', 'os', 'device', 'country', 'country_code', 'city', 'isp'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Writes the sign-in at once; the approximate place is looked up after the response has gone out,
     * so a slow lookup never holds up the person signing in.
     */
    public static function record(Request $request, User $user, string $portal): void
    {
        try {
            $signIn = self::create([
                'user_id' => $user->id,
                'username' => mb_substr((string) $user->username, 0, 60),
                'name' => mb_substr((string) $user->name, 0, 120),
                'role' => $user->role,
                'portal' => $portal,
                'ip' => $request->ip(),
            ] + UserAgent::parse($request->userAgent()));

            $ip = $request->ip();
            app()->terminating(function () use ($signIn, $ip) {
                try {
                    $signIn->update(GeoIp::lookup($ip));
                    self::where('created_at', '<', now()->subDays(max(1, (int) config('services.security_events.keep_days'))))->delete();
                } catch (Throwable) {
                    // the place is a nice to have
                }
            });
        } catch (Throwable) {
            // a log that fails must never stop someone from signing in
        }
    }

    public function place(): ?string
    {
        $parts = array_filter([$this->city, $this->country]);

        return $parts ? implode(', ', $parts) : null;
    }
}
