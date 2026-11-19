<?php

namespace App\Http\Middleware;

use App\Support\HoneypotTrap;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a guest's honeypot hold is running, every page of the shop answers with the trap.
 * Signed-in users are never held. Runs after the session starts, so it can tell who is signed in.
 */
class TrapHoneypotVisitors
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! HoneypotTrap::enabled() || HoneypotTrap::isTrapPath($request) || $request->is('up')) {
            return $next($request);
        }

        if (HoneypotTrap::remaining($request) > 0 && $request->user() === null) {
            return redirect(HoneypotTrap::HOME_PATH, 303)->header('Cache-Control', 'no-store');
        }

        return $next($request);
    }
}
