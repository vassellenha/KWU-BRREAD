<?php

namespace Tests\Unit;

use App\Support\UserAgent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserAgentTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function agents(): array
    {
        return [
            'chrome on windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'Chrome 142', 'Windows 10/11', 'Desktop'],
            'edge on windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 'Edge 142', 'Windows 10/11', 'Desktop'],
            'safari on iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1', 'Safari 18', 'iOS 18.1', 'Mobile (iPhone)'],
            'samsung android' => ['Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 'Chrome 141', 'Android 14', 'Mobile (SM-S918B)'],
            'firefox on mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:133.0) Gecko/20100101 Firefox/133.0', 'Firefox 133', 'macOS 10.15', 'Desktop'],
            'sqlmap' => ['sqlmap/1.8.11#stable (https://sqlmap.org)', 'sqlmap 1.8.11', 'Unknown OS', 'Script or tool'],
            'curl' => ['curl/8.5.0', 'curl 8.5.0', 'Unknown OS', 'Script or tool'],
            'python requests' => ['python-requests/2.32.3', 'Python requests 2.32.3', 'Unknown OS', 'Script or tool'],
            'empty' => ['', 'None sent', 'Unknown', 'Script or tool'],
        ];
    }

    #[DataProvider('agents')]
    public function test_it_reads_browser_system_and_device(string $agent, string $browser, string $os, string $device): void
    {
        $this->assertSame(['browser' => $browser, 'os' => $os, 'device' => $device], UserAgent::parse($agent));
    }
}
