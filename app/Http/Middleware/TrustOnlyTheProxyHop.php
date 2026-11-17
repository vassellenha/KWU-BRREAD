<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins the visitor's address before anything reads it. Laravel takes the LEFT-most
 * X-Forwarded-For entry, which a visitor can write themselves, so one forged header could pick
 * the address the rate limits, the lockdown, the IP blocks and the security log see.
 *
 * Railway's edge sets X-Real-IP to the address it actually saw and overwrites any copy the
 * visitor sends. It also appends its own edge address (a public Singapore IP) to the END of
 * X-Forwarded-For, so "the right-most public entry" would be Railway, not the visitor. When
 * X-Real-IP is there it wins; without it (local work, another host) the right-most public entry
 * of the chain is used, as before.
 */
class TrustOnlyTheProxyHop
{
    public function handle(Request $request, Closure $next): Response
    {
        $real = trim((string) $request->headers->get('X-Real-IP', ''));
        $chain = $request->headers->get('X-Forwarded-For');

        if (filter_var($real, FILTER_VALIDATE_IP) !== false) {
            $request->headers->set('X-Forwarded-For', $real);
        } elseif (is_string($chain) && $chain !== '') {
            $request->headers->set('X-Forwarded-For', self::visitor($chain));
        }

        return $next($request);
    }

    /**
     * The right-most public address of the chain. A chain with no public address (local
     * development, tests) keeps its first entry.
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
