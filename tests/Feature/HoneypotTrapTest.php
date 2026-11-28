<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Support\HoneypotTrap;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HoneypotTrapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(UserSeeder::class);
        config(['auth.honeypot_trap' => true, 'auth.login_prank' => true]);
    }

    private function heldSince(): int
    {
        return (int) Cache::get('honeypot-hold:127.0.0.1');
    }

    /** Pretends the hold began this many seconds ago. */
    private function elapsed(int $seconds): void
    {
        Cache::put('honeypot-hold:127.0.0.1', time() - $seconds, 120);
    }

    public function test_an_allowed_address_is_never_trapped_or_held(): void
    {
        config(['auth.honeypot_allowed_ips' => ['127.0.0.1']]);

        $this->get('/admin')->assertNotFound()->assertDontSee('data-phase', false);
        $this->assertSame(0, $this->heldSince());
        $this->assertSame(0, SecurityEvent::where('reason', 'honeypot')->count());

        // even a hold left over from before the address was allowed no longer redirects it
        $this->elapsed(5);
        $this->get('/superadmin')->assertOk();
    }

    public function test_the_team_addresses_are_on_the_default_allow_list(): void
    {
        $default = require config_path('auth.php');

        foreach (['103.171.164.172', '36.68.86.63', '182.10.66.205'] as $address) {
            $this->assertContains($address, $default['honeypot_allowed_ips']);
        }

        config(['auth.honeypot_allowed_ips' => $default['honeypot_allowed_ips']]);

        // Atmint Chicarron's address meets a plain 404 at /admin and starts no hold
        $this->withServerVariables(['REMOTE_ADDR' => '182.10.66.205'])->get('/admin')->assertNotFound();
        $this->assertSame(0, (int) Cache::get('honeypot-hold:182.10.66.205'));
    }

    public function test_admin_starts_the_hold_in_the_horror_phase(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('data-phase="horror"', false)
            ->assertSee('data-elapsed="0"', false)
            ->assertSee('data-horror="30"', false)
            ->assertSee('data-kewer="30"', false)
            ->assertSee('prank-kewer.mp4', false)
            ->assertDontSee('<a ', false)
            ->assertDontSee('/superadmin');

        $this->assertEqualsWithDelta(time(), $this->heldSince(), 2);
        $this->assertSame(1, SecurityEvent::where('reason', 'honeypot')->count());
    }

    public function test_under_thirty_seconds_in_is_the_horror_phase(): void
    {
        $this->get('/admin')->assertOk();
        $this->elapsed(9);

        $this->get('/admin')->assertOk()->assertSee('data-phase="horror"', false)->assertSee('data-elapsed="9"', false);
    }

    public function test_from_thirty_seconds_in_it_is_the_kewer_phase(): void
    {
        $this->get('/admin')->assertOk();

        foreach ([30, 45, 59] as $seconds) {
            $this->elapsed($seconds);
            $this->get('/admin')->assertOk()->assertSee('data-phase="kewer"', false)->assertSee('data-elapsed="'.$seconds.'"', false);
        }

        $this->assertSame(1, SecurityEvent::where('reason', 'honeypot')->count());
    }

    public function test_a_refresh_at_second_forty_five_resumes_in_kewer_and_never_replays_the_horror(): void
    {
        $this->get('/admin')->assertOk();
        $this->elapsed(45);

        $this->get('/admin')->assertOk()->assertSee('data-phase="kewer"', false);
        $this->get('/admin')->assertOk()->assertSee('data-phase="kewer"', false);
        $this->get('/admin/other')->assertOk()->assertSee('data-phase="kewer"', false);
        $this->assertEqualsWithDelta(time() - 45, $this->heldSince(), 2);
    }

    public function test_the_hold_ends_at_sixty_seconds_and_releases_to_home(): void
    {
        $this->get('/admin')->assertOk();
        $this->elapsed(60);

        $this->get('/menu')->assertOk();
        $this->get('/admin')->assertOk()->assertSee("location.replace('/')", false)->assertDontSee('hp-windows');
        $this->get('/menu')->assertOk();
    }

    public function test_the_trap_response_has_no_leave_site_prompt_or_music(): void
    {
        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertStringNotContainsString('beforeunload', $html);
        $this->assertStringNotContainsString('returnValue', $html);
        $this->assertStringNotContainsString('data-music', $html);
        $this->assertStringContainsString('BRREAD! COLD ROOM 03', $html);
        // The Kewer scene has only the floating terminal windows, no separate line from the owner.
        $this->assertStringNotContainsString('hp-say', $html);
        $this->assertStringNotContainsString('Mana Mungkin', $html);
        $this->assertStringNotContainsString('hp-center', $html);
    }

    public function test_the_lengths_come_from_config(): void
    {
        config(['auth.honeypot_horror_seconds' => 5, 'auth.honeypot_kewer_seconds' => 10]);
        $this->get('/admin')->assertOk()->assertSee('data-horror="5"', false)->assertSee('data-kewer="10"', false);
        $this->elapsed(6);
        $this->get('/admin')->assertOk()->assertSee('data-phase="kewer"', false);
        $this->elapsed(15);
        $this->get('/menu')->assertOk();
    }

    public function test_every_honeypot_address_and_sub_path_sets_the_hold(): void
    {
        foreach (['/admin/login', '/admin/anything/deeper', '/administrator', '/wp-admin', '/wp-admin/setup.php'] as $path) {
            Cache::flush();
            $this->get($path)->assertOk()->assertSee('hp-windows');
        }
    }

    public function test_a_second_request_keeps_counting_down_instead_of_restarting(): void
    {
        $this->get('/admin')->assertOk();
        $this->elapsed(12);

        $this->get('/admin')->assertOk()->assertSee('data-elapsed="12"', false);
        $this->get('/admin/x')->assertOk()->assertSee('data-elapsed="12"', false);
        $this->assertSame(1, SecurityEvent::where('reason', 'honeypot')->count());
    }

    public function test_any_other_address_goes_back_to_the_trap_while_the_hold_runs(): void
    {
        $this->get('/admin')->assertOk();

        foreach (['/', '/menu', '/builder', '/about', '/staff', '/superadmin', '/staff/orders', '/no/such/page'] as $path) {
            $this->get($path)->assertRedirect('/admin');
        }

        $this->post('/staff', ['username' => 'x', 'password' => 'y'])->assertRedirect('/admin');
        $this->get('/up')->assertOk();
    }

    public function test_the_guest_is_released_to_home_after_the_hold_and_back_does_not_restart_it(): void
    {
        $this->get('/admin')->assertOk();
        Cache::forget('honeypot-hold:127.0.0.1');

        $this->get('/')->assertOk();
        $this->get('/menu')->assertOk();

        // Back to /admin right after: the browser is only sent on to Home, no new hold.
        $this->get('/admin')->assertOk()->assertSee("location.replace('/')", false)->assertDontSee('hp-windows');
        $this->assertSame(0, HoneypotTrap::remaining(request()));
        $this->get('/menu')->assertOk();
    }

    public function test_a_browser_resuming_an_ended_hold_does_not_start_a_new_one(): void
    {
        $this->get('/admin?resume=1')->assertOk()->assertSee("location.replace('/')", false)->assertDontSee('hp-windows');
        $this->get('/menu')->assertOk();
        $this->assertSame(0, SecurityEvent::where('reason', 'honeypot')->count());
    }

    public function test_a_signed_in_user_is_never_trapped(): void
    {
        $this->actingAs(User::where('username', 'staffmrp')->firstOrFail());

        $this->get('/admin')->assertOk()->assertDontSee('hp-windows');
        $this->get('/staff/orders')->assertOk();
        $this->assertSame(0, $this->heldSince());
    }

    public function test_a_hold_does_not_trap_someone_who_signed_in_meanwhile(): void
    {
        $this->get('/admin')->assertOk();
        $this->actingAs(User::where('username', 'staffmrp')->firstOrFail());

        $this->get('/menu')->assertOk();
    }

    public function test_the_login_pages_work_with_no_hold(): void
    {
        $this->get('/superadmin')->assertOk()->assertSee('Superadmin');
        $this->get('/staff')->assertOk();
        $this->post('/staff', ['username' => 'staffmrp', 'password' => '12345678'])->assertRedirect('/staff/orders');
    }

    public function test_the_wrong_password_ladder_is_untouched_by_the_trap(): void
    {
        $this->get('/admin')->assertOk();
        Cache::forget('honeypot-hold:127.0.0.1');

        for ($i = 1; $i <= 3; $i++) {
            $this->postJson('/staff', ['username' => 'staffmrp', 'password' => 'wrong'])->assertStatus(422)->assertJsonPath('prank.stage', $i);
        }

        $this->postJson('/staff', ['username' => 'staffmrp', 'password' => 'wrong'])->assertJsonPath('prank.stage', 4);
        $this->assertSame(0, HoneypotTrap::remaining(request()));
    }

    public function test_only_a_browser_that_signed_in_as_superadmin_is_sent_back_to_superadmin(): void
    {
        config(['auth.honeypot_trap' => false]);

        // Plain visitor first: a cookie set with withCookie() stays on the test client for the next requests.
        $this->get('/staff/orders')->assertRedirect('/staff');
        $this->withCookie('portal', 'admin')->get('/staff/security')->assertRedirect('/superadmin');
    }

    public function test_the_extra_bait_addresses_start_the_hold(): void
    {
        foreach (['/login', '/dashboard', '/panel', '/cpanel', '/phpmyadmin', '/backend', '/phpmyadmin/index.php', '/backend/users'] as $path) {
            Cache::flush();
            $this->get($path)->assertOk()->assertSee('hp-windows');
            $this->assertGreaterThan(0, $this->heldSince());
        }
    }

    public function test_the_real_sign_in_addresses_are_not_bait(): void
    {
        $this->get('/staff')->assertOk()->assertDontSee('hp-windows');
        $this->get('/superadmin')->assertOk()->assertDontSee('hp-windows');
        $this->assertSame(0, $this->heldSince());
    }

    public function test_no_bait_address_ever_redirects_and_every_answer_is_never_cached(): void
    {
        foreach (HoneypotTrap::PATHS as $path) {
            Cache::flush();
            $trap = $this->get('/'.$path);
            $trap->assertOk();
            $this->assertStringContainsString('no-store', (string) $trap->headers->get('Cache-Control'));

            // while the hold runs, a held visitor is only ever sent back with a 303, never a permanent redirect
            $this->get('/menu')->assertStatus(303)->assertRedirect('/admin')->assertHeader('Cache-Control', 'no-store, private');
        }

        Cache::flush();
        $this->get('/admin')->assertOk();
        Cache::forget('honeypot-hold:127.0.0.1');
        $released = $this->get('/admin');
        $released->assertOk();
        $this->assertStringContainsString('no-store', (string) $released->headers->get('Cache-Control'));

        config(['auth.honeypot_trap' => false]);
        $off = $this->get('/admin');
        $off->assertNotFound();
        $this->assertStringContainsString('no-store', (string) $off->headers->get('Cache-Control'));
    }

    public function test_replay_ignores_the_grace_period_outside_production(): void
    {
        $this->get('/admin')->assertOk();
        Cache::forget('honeypot-hold:127.0.0.1');

        // inside the grace period the address is left alone ...
        $this->get('/admin')->assertOk()->assertDontSee('hp-windows');
        $this->assertSame(0, $this->heldSince());

        // ... until a developer asks for a replay
        $this->assertFalse(app()->environment('production'));
        $this->get('/admin?replay=1')->assertOk()->assertSee('hp-windows')->assertSee('data-elapsed="0"', false);
        $this->assertGreaterThan(0, $this->heldSince());
    }

    public function test_replay_restarts_a_finished_hold_even_when_a_browser_is_resuming(): void
    {
        $this->get('/admin')->assertOk();
        $this->elapsed(75);

        $this->get('/admin?resume=1&replay=1')->assertOk()->assertSee('data-elapsed="0"', false);
    }

    public function test_replay_is_ignored_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->get('/admin')->assertOk();
        Cache::forget('honeypot-hold:127.0.0.1');

        $this->get('/admin?replay=1')->assertOk()->assertSee("location.replace('/')", false)->assertDontSee('hp-windows');
        $this->assertSame(0, $this->heldSince());

        // a hold that is running is not restarted by it either
        Cache::flush();
        $this->get('/admin')->assertOk();
        $this->elapsed(12);
        $this->get('/admin?replay=1')->assertOk()->assertSee('data-elapsed="12"', false);
    }

    public function test_seek_freezes_a_replay_outside_production_only(): void
    {
        $this->get('/admin?replay=1&seek=25.9')->assertOk()->assertSee('data-seek="25.9"', false);

        // not without replay, not past the cutscene, not with junk
        Cache::flush();
        $this->get('/admin?seek=12')->assertOk()->assertDontSee('data-seek', false);
        Cache::flush();
        $this->get('/admin?replay=1&seek=31')->assertOk()->assertDontSee('data-seek', false);
        Cache::flush();
        $this->get('/admin?replay=1&seek=abc')->assertOk()->assertDontSee('data-seek', false);
    }

    public function test_seek_is_ignored_in_production(): void
    {
        $this->app['env'] = 'production';

        // without data-seek the browser never builds the QA hooks (window.__hp), so the live page cannot be frozen or sampled
        $this->get('/admin?replay=1&seek=12')->assertOk()->assertDontSee('data-seek', false);
        $this->get('/admin?seek=12')->assertOk()->assertDontSee('data-seek', false);
    }

    public function test_the_page_lists_only_the_sound_files_that_exist(): void
    {
        // The page hands over exactly what is on disk (the real recordings are bundled), never a guess.
        $this->get('/admin')->assertOk()->assertSee('data-samples="'.e(json_encode(HoneypotTrap::samples(), JSON_UNESCAPED_SLASHES)).'"', false);

        // The scan itself runs against a scratch folder, so no real recording is touched.
        $dir = sys_get_temp_dir().'/honeypot-samples-'.uniqid();
        mkdir($dir, 0777, true);
        file_put_contents($dir.'/gunshot.mp3', 'x');
        file_put_contents($dir.'/zap.ogg', 'x');
        file_put_contents($dir.'/not-a-known-sound.mp3', 'x');

        try {
            $this->assertSame(['gunshot' => '/audio/honeypot/gunshot.mp3', 'zap' => '/audio/honeypot/zap.ogg'], HoneypotTrap::samples($dir));
            $this->assertSame([], HoneypotTrap::samples($dir.'/missing'));
        } finally {
            array_map('unlink', glob($dir.'/*') ?: []);
            @rmdir($dir);
        }
    }

    public function test_the_flag_turns_the_trap_off(): void
    {
        config(['auth.honeypot_trap' => false]);

        $this->get('/admin')->assertNotFound();
        $this->get('/administrator')->assertNotFound();
        $this->get('/menu')->assertOk();
        $this->assertSame(0, $this->heldSince());
    }

    public function test_a_signed_in_admin_can_watch_the_whole_trap_at_the_unlisted_preview_without_a_hold(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();

        $this->actingAs($admin)->get('/'.config('auth.honeypot_preview_path'))
            ->assertOk()
            ->assertSee('data-phase="horror"', false)
            ->assertSee('data-elapsed="0"', false)
            ->assertSee('data-preview="1"', false);

        // nothing is held or logged, so nobody is sent back to /admin and the shop stays open
        $this->assertSame(0, $this->heldSince());
        $this->assertSame(0, SecurityEvent::where('reason', 'honeypot')->count());
        $this->actingAs($admin)->get('/menu')->assertOk();
    }

    public function test_the_trap_preview_is_a_404_for_guests_and_staff_and_the_real_page_never_carries_the_preview_mark(): void
    {
        $path = '/'.config('auth.honeypot_preview_path');

        $this->get($path)->assertNotFound();
        $this->assertSame(0, $this->heldSince());

        $this->get('/admin')->assertOk()->assertDontSee('data-preview', false);

        $this->actingAs(User::where('username', 'staffmrp')->firstOrFail())->get($path)->assertNotFound();
    }
}
