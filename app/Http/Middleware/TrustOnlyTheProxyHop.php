<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The app trusts every proxy (the host's edge terminates HTTPS), and with that Laravel takes the
 * LEFT-most X-Forwarded-For entry as the visitor. That entry is whatever the visitor wrote, so a
 * single header (X-Forwarded-For: 1.2.3.4) could pick the address the rate limits, the lockdown,
 * the IP blocks and the security log see: dodge a block, or lock someone else out by borrowing
 * their address. The edge appends the address it actually saw on the RIGHT, so this keeps only that
 * one (skipping the host's own private hops) before anything reads the header.
 */
class TrustOnlyTheProxyHop
{
    public function handle(Request $request, Closure $next): Response
    {
        $chain = $request->headers->get('X-Forwarded-For');

        if (is_string($chain) && $chain !== '') {
            $request->headers->set('X-Forwarded-For', self::visitor($chain));
        }

        return $next($request);
    }

    /**
     * The right-most public address of the chain. A chain with no public address (local
     * development, tests) keeps its first entry, as before.
     */
    public static function visitor(string $chain): string
    {
        $entries = array_values(array_filter(array_map('trim', explode(',', $chain)), fn ($entry) => $entry !== ''));

        if ($entries === []) {
            return $chain;
        }

        foreach (array_reverse($entries) as $entry) {
            if (self::isPublic($entry)) {
                return $entry;
            }
        }

        return $entries[0];
    }

    private static function isPublic(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        // 100.64.0.0/10 (carrier-grade NAT), where hosting platforms keep their internal hops.
        $long = ip2long($ip);

        return $long === false || ($long & 0xFFC00000) !== 0x64400000;
    }
}
