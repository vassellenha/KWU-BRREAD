<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Support\GeoIp;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityEventsTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(UserSeeder::class);
        Cache::flush();
        Http::preventStrayRequests();
    }

    private function fakeGeo(): void
    {
        config(['services.geoip.enabled' => true]);
        Http::fake(['ip-api.com/*' => Http::response([
            'status' => 'success', 'country' => 'Indonesia', 'countryCode' => 'ID', 'city' => 'Jakarta', 'isp' => 'PT Telkom Indonesia',
        ])]);
    }

    private function attack(string $ip = '36.66.1.2'): \Illuminate\Testing\TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('User-Agent', self::CHROME)
            ->postJson('/staff', ['username' => "admin' OR 1=1--", 'password' => 'x']);
    }

    public function test_a_payload_is_recorded_with_browser_system_device_and_place(): void
    {
        $this->fakeGeo();

        $this->attack()->assertStatus(429)
            ->assertJsonPath('trace.city', 'Jakarta')
            ->assertJsonPath('trace.browser', 'Chrome 142')
            ->assertJsonPath('trace.ip', '36.66.1.2');

        $event = SecurityEvent::sole();
        $this->assertSame('payload', $event->reason);
        $this->assertSame('36.66.1.2', $event->ip);
        $this->assertSame('Chrome 142', $event->browser);
        $this->assertSame('Windows 10/11', $event->os);
        $this->assertSame('Desktop', $event->device);
        $this->assertSame('Jakarta, Indonesia', $event->place());
        $this->assertSame('PT Telkom Indonesia', $event->isp);
        $this->assertSame("admin' OR 1=1--", $event->username);
    }

    public function test_the_place_is_looked_up_once_per_address(): void
    {
        $this->fakeGeo();

        $this->attack();
        $this->attack();
        $this->attack();

        Http::assertSentCount(1);
    }

    public function test_repeats_from_a_locked_address_add_hits_instead_of_rows(): void
    {
        $this->attack();
        $this->attack();
        $this->attack();

        $this->assertSame(1, SecurityEvent::count());
        $this->assertSame(3, SecurityEvent::sole()->hits);
    }

    public function test_private_addresses_and_a_failing_lookup_never_break_the_lockdown(): void
    {
        config(['services.geoip.enabled' => true]);
        Http::fake(['ip-api.com/*' => Http::response('down', 500)]);

        $this->attack('8.8.4.4')->assertStatus(429);
        $this->assertNull(SecurityEvent::sole()->country);

        $this->assertSame(['country' => null, 'country_code' => null, 'city' => null, 'isp' => null], GeoIp::lookup('192.168.1.5'));
    }

    public function test_the_superadmin_sees_the_security_page_with_the_event(): void
    {
        $this->fakeGeo();
        $this->attack();

        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $this->actingAs($admin)->get('/staff/security')
            ->assertOk()
            ->assertSee('36.66.1.2')
            ->assertSee('Jakarta, Indonesia')
            ->assertSee('PT Telkom Indonesia')
            ->assertSee('Chrome 142')
            ->assertSee("admin&#039; OR 1=1--", false)
            ->assertDontSee("admin' OR 1=1--", false);

        $this->actingAs($admin)->get('/staff/orders')->assertSee('Security');
    }

    public function test_staff_cannot_open_the_security_page(): void
    {
        $staff = User::where('username', 'staffmrp')->first();

        $this->actingAs($staff)->get('/staff/security')->assertForbidden();
        $this->actingAs($staff)->get('/staff/orders')->assertDontSee('staff/security');
    }

    public function test_old_events_are_pruned_eventually(): void
    {
        config(['services.security_events.keep_days' => 90]);
        $old = SecurityEvent::create(['reason' => 'payload', 'ip' => '1.2.3.4', 'last_seen_at' => now()->subDays(100)]);
        $old->forceFill(['created_at' => now()->subDays(100)])->save();

        // Pruning runs on a random draw, so keep triggering from fresh addresses until it has run.
        for ($i = 1; $i <= 400 && SecurityEvent::whereKey($old->id)->exists(); $i++) {
            Cache::flush();
            $this->attack("9.9.{$i}.1");
        }

        $this->assertFalse(SecurityEvent::whereKey($old->id)->exists());
    }

    public function test_the_privacy_page_explains_the_security_records(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('Security records');
    }
}
