<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

/**
 * The normal session start, except that a browser which signed in at /superadmin (it carries the "portal=admin"
 * cookie) and reaches us from one of the owner's own addresses keeps its session for weeks instead of the usual idle
 * lifetime. This never skips the sign-in: without a valid session there is still the password and the captcha.
 * The address is $request->ip(), already pinned to the proxy hop by TrustOnlyTheProxyHop.
 */
class StartSessionForTrustedIp extends StartSession
{
    public function handle($request, Closure $next)
    {
        if (self::extends($request)) {
            config(['session.lifetime' => self::longLifetimeMinutes()]);
        }

        return parent::handle($request, $next);
    }

    public static function extends(Request $request): bool
    {
        return $request->cookie('portal') === 'admin'
            && in_array($request->ip(), (array) config('auth.superadmin_trusted_ips', []), true);
    }

    public static function longLifetimeMinutes(): int
    {
        return max(1, (int) config('auth.superadmin_session_days', 30)) * 24 * 60;
    }

    /**
     * Expired rows are swept with the LONG lifetime, otherwise any ordinary visitor's request that hits the
     * lottery would delete a trusted superadmin session older than the normal idle time. (Ordinary sessions still
     * expire on read; they only stay in the table a little longer.)
     */
    protected function collectGarbage(Session $session)
    {
        $config = $this->manager->getSessionConfig();

        if ($this->configHitsLottery($config)) {
            $session->getHandler()->gc(max(
                (int) ($config['lifetime'] ?? 0),
                (int) config('auth.superadmin_session_days', 30) * 24 * 60,
            ) * 60);
        }
    }
}
