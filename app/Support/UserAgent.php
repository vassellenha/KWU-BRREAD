<?php

namespace App\Support;

/**
 * A small User-Agent reader: browser, operating system and kind of device. The header is
 * whatever the client chooses to send, so this describes the claim, not a verified fact.
 * Attack tools usually announce themselves here, which is worth recording on its own.
 */
class UserAgent
{
    private const TOOLS = [
        'sqlmap' => 'sqlmap',
        'nikto' => 'Nikto',
        'nmap' => 'Nmap',
        'hydra' => 'Hydra',
        'wpscan' => 'WPScan',
        'nuclei' => 'Nuclei',
        'zgrab' => 'ZGrab',
        'gobuster' => 'Gobuster',
        'ffuf' => 'ffuf',
        'burp' => 'Burp Suite',
        'curl/' => 'curl',
        'wget/' => 'Wget',
        'python-requests' => 'Python requests',
        'python-urllib' => 'Python urllib',
        'aiohttp' => 'Python aiohttp',
        'go-http-client' => 'Go HTTP client',
        'postmanruntime' => 'Postman',
        'insomnia' => 'Insomnia',
        'okhttp' => 'OkHttp',
        'java/' => 'Java',
        'headlesschrome' => 'Headless Chrome',
        'phantomjs' => 'PhantomJS',
        'node-fetch' => 'node-fetch',
        'axios/' => 'axios',
    ];

    /**
     * @return array{browser: string, os: string, device: string}
     */
    public static function parse(?string $agent): array
    {
        $agent = trim((string) $agent);

        if ($agent === '') {
            return ['browser' => 'None sent', 'os' => 'Unknown', 'device' => 'Script or tool'];
        }

        $lower = strtolower($agent);

        foreach (self::TOOLS as $needle => $name) {
            if (str_contains($lower, $needle)) {
                return [
                    'browser' => $name.self::version($agent, preg_quote(rtrim($needle, '/'), '/')),
                    'os' => self::os($agent),
                    'device' => 'Script or tool',
                ];
            }
        }

        return [
            'browser' => self::browser($agent),
            'os' => self::os($agent),
            'device' => self::device($agent),
        ];
    }

    private static function browser(string $agent): string
    {
        $checks = [
            'Edge' => 'Edg(?:e|A|iOS)?',
            'Opera' => 'OPR|Opera',
            'Samsung Internet' => 'SamsungBrowser',
            'Firefox' => 'Firefox|FxiOS',
            'Chrome' => 'Chrome|CriOS',
            'Safari' => 'Version(?=\/[\d.]+.*Safari)',
        ];

        foreach ($checks as $name => $pattern) {
            if (preg_match('/(?:'.$pattern.')\/(\d+)/i', $agent, $match)) {
                return "{$name} {$match[1]}";
            }
        }

        return 'Unknown browser';
    }

    private static function os(string $agent): string
    {
        return match (true) {
            (bool) preg_match('/Windows NT 10/i', $agent) => 'Windows 10/11',
            (bool) preg_match('/Windows NT 6\.3/i', $agent) => 'Windows 8.1',
            (bool) preg_match('/Windows NT 6\.1/i', $agent) => 'Windows 7',
            (bool) preg_match('/Windows/i', $agent) => 'Windows',
            (bool) preg_match('/Android (\d+(?:\.\d+)?)/i', $agent, $m) => 'Android '.$m[1],
            (bool) preg_match('/(?:iPhone|CPU) OS (\d+)_?(\d+)?/i', $agent, $m) => 'iOS '.$m[1].(isset($m[2]) && $m[2] !== '' ? '.'.$m[2] : ''),
            (bool) preg_match('/iPad/i', $agent) => 'iPadOS',
            (bool) preg_match('/Mac OS X (\d+)[_.](\d+)/i', $agent, $m) => 'macOS '.$m[1].'.'.$m[2],
            (bool) preg_match('/CrOS/i', $agent) => 'ChromeOS',
            (bool) preg_match('/Ubuntu/i', $agent) => 'Ubuntu',
            (bool) preg_match('/Kali/i', $agent) => 'Kali Linux',
            (bool) preg_match('/Linux/i', $agent) => 'Linux',
            default => 'Unknown OS',
        };
    }

    private static function device(string $agent): string
    {
        if (preg_match('/iPad|Tablet|SM-T\d+/i', $agent)) {
            return 'Tablet';
        }

        if (preg_match('/iPhone/i', $agent)) {
            return 'Mobile (iPhone)';
        }

        // Android puts the model name after the version: "Android 14; SM-S918B Build/..." or "...; SM-S918B)".
        if (preg_match('/Android [\d.]+;\s*([^;)]+?)(?:\s+Build\/[^;)]*)?[;)]/i', $agent, $match)) {
            $model = trim($match[1]);

            return $model !== '' && strtolower($model) !== 'k' ? "Mobile ({$model})" : 'Mobile (Android)';
        }

        if (preg_match('/Mobile/i', $agent)) {
            return 'Mobile';
        }

        return 'Desktop';
    }

    private static function version(string $agent, string $needle): string
    {
        return preg_match('/'.$needle.'\/([\d.]+)/i', $agent, $match) ? ' '.$match[1] : '';
    }
}
