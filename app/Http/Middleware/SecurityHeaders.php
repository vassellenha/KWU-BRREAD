<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side hardening for every response: no framing (clickjacking), no MIME sniffing,
 * a short referrer, locked-down device APIs, HSTS over HTTPS, and a Content-Security-Policy
 * that only lets the page load from this site plus Google Fonts.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=15552000; includeSubDomains';
        }

        // The Vite dev server (public/hot exists) serves scripts from another origin, so the
        // policy only applies to built assets. Alpine evaluates expressions, hence 'unsafe-eval'.
        if (! file_exists(public_path('hot'))) {
            $headers['Content-Security-Policy'] = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' https://fonts.gstatic.com data:",
                "img-src 'self' data: blob:",
                "connect-src 'self'",
                "worker-src 'self' blob:",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
            ]);
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
