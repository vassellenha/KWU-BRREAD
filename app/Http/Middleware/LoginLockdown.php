<?php

namespace App\Http\Middleware;

use App\Support\Lockdown;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps locked-out addresses away from the sign-in forms and locks an address the moment it
 * sends an attack payload as a username.
 */
class LoginLockdown
{
    public function handle(Request $request, Closure $next): Response
    {
        $left = Lockdown::remaining($request);

        if ($left > 0) {
            Lockdown::recordRepeat($request);

            return Lockdown::response($request, $left, 'locked');
        }

        if ($request->isMethod('POST') && Lockdown::looksLikeAttack($request->input('username'))) {
            Lockdown::trigger($request, 'payload', Lockdown::PAYLOAD_SECONDS);

            return Lockdown::response($request, Lockdown::PAYLOAD_SECONDS, 'payload');
        }

        return $next($request);
    }
}
