<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Approximate place for an IP address: country, city and network provider. It comes from
 * ip-api.com and is city-level at best; behind a VPN, proxy or Tor it names the exit server,
 * not the person. Results are cached for a day so a flood from one address costs one lookup.
 */
class GeoIp
{
    /**
     * @return array{country: ?string, country_code: ?string, city: ?string, isp: ?string}
     */
    public static function lookup(?string $ip): array
    {
        $empty = ['country' => null, 'country_code' => null, 'city' => null, 'isp' => null];

        if (! config('services.geoip.enabled') || ! self::isPublic($ip)) {
            return $empty;
        }

        return Cache::remember('geoip:'.$ip, now()->addDay(), function () use ($ip, $empty) {
            try {
                // The free tier only answers over plain HTTP; the lookup carries nothing but the IP.
                $response = Http::timeout(2)->connectTimeout(2)
                    ->get('http://ip-api.com/json/'.urlencode($ip), ['fields' => 'status,country,countryCode,city,isp']);

                if (! $response->ok() || $response->json('status') !== 'success') {
                    return $empty;
                }

                return [
                    'country' => self::clean($response->json('country'), 80),
                    'country_code' => self::clean($response->json('countryCode'), 2),
                    'city' => self::clean($response->json('city'), 80),
                    'isp' => self::clean($response->json('isp'), 120),
                ];
            } catch (Throwable) {
                return $empty;
            }
        });
    }

    public static function isPublic(?string $ip): bool
    {
        return is_string($ip)
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private static function clean(mixed $value, int $max): ?string
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, $max) : null;
    }
}
