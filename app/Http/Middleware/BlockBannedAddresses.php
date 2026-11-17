<?php

namespace App\Http\Middleware;

use App\Models\IpBlock;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Turns away every request from an address the superadmin blocked, on every page, until
 * the block is lifted on the Security page (or with `php artisan security:unblock`).
 */
class BlockBannedAddresses
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        if ($request->is('up') || ! IpBlock::isBlocked($ip)) {
            return $next($request);
        }

        // Count the knocks, at most one write a minute per address.
        try {
            if (Cache::add('ip-block-seen:'.$ip, true, 60)) {
                IpBlock::where('ip', $ip)->first()?->increment('hits', 1, ['last_seen_at' => now()]);
            }
        } catch (Throwable) {
            // Counting must never stand in the way of the block itself.
        }

        return response()->view('errors.blocked', ['ip' => $ip], 403);
    }
}
