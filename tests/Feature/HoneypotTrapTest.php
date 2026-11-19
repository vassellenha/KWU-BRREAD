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

    public function test_admin_starts_the_hold_in_the_horror_phase(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('data-phase="horror"', false)
            ->assertSee('data-elapsed="0"', false)
            ->assertSee('data-horror="20"', false)
            ->assertSee('data-kewer="30"', false)
            ->assertSee('prank-kewer.mp4', false)
            ->assertDontSee('<a ', false)
            ->assertDontSee('/superadmin');

        $this->assertEqualsWithDelta(time(), $this->heldSince(), 2);
        $this->assertSame(1, SecurityEvent::where('reason', 'honeypot')->count());
    }

    public function test_under_twenty_seconds_in_is_the_horror_phase(): void
    {
        $this->get('/admin')->assertOk();
        $this->elapsed(9);

        $this->get('/admin')->assertOk()->assertSee('data-phase="horror"', false)->assertSee('data-elapsed="9"', false);
    }

    public function test_from_twenty_seconds_in_it_is_the_kewer_phase(): void
    {
        $this->get('/admin')->assertOk();

        foreach ([20, 27, 49] as $seconds) {
            $this->elapsed($seconds);
            $this->get('/admin')->assertOk()->assertSee('data-phase="kewer"', false)->assertSee('data-elapsed="'.$seconds.'"', false);
        }

        $this->assertSame(1, SecurityEvent::where('reason', 'honeypot')->count());
    }

    public function test_a_refresh_at_second_twenty_five_resumes_in_kewer_and_never_replays_the_horror(): void
    {
        $this->get('/admin')->assertOk();
        $this->elapsed(27);

        $this->get('/admin')->assertOk()->assertSee('data-phase="kewer"', false);
        $this->get('/admin')->assertOk()->assertSee('data-phase="kewer"', false);
        $this->get('/admin/other')->assertOk()->assertSee('data-phase="kewer"', false);
        $this->assertEqualsWithDelta(time() - 27, $this->heldSince(), 2);
    }

    public function test_the_hold_ends_at_fifty_seconds_and_releases_to_home(): void
    {
        $this->get('/admin')->assertOk();
        $this->elapsed(50);

        $this->get('/menu')->assertOk();
        $this->get('/admin')->assertOk()->assertSee("location.replace('/')", false)->assertDontSee('Amatiran');
        $this->get('/menu')->assertOk();
    }

    public function test_the_trap_response_has_no_leave_site_prompt_or_music(): void
    {
        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertStringNotContainsString('beforeunload', $html);
        $this->assertStringNotContainsString('returnValue', $html);
        $this->assertStringNotContainsString('data-music', $html);
        $this->assertStringContainsString('BRREAD! COLD ROOM 03', $html);
        $this->assertStringContainsString('Mana Mungkin Seorang &quot;Vassell Enha&quot;', $html);
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
            $this->get($path)->assertOk()->assertSee('Amatiran');
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
        $this->get('/admin')->assertOk()->assertSee("location.replace('/')", false)->assertDontSee('Amatiran');
        $this->assertSame(0, HoneypotTrap::remaining(request()));
        $this->get('/menu')->assertOk();
    }

    public function test_a_browser_resuming_an_ended_hold_does_not_start_a_new_one(): void
    {
        $this->get('/admin?resume=1')->assertOk()->assertSee("location.replace('/')", false)->assertDontSee('Amatiran');
        $this->get('/menu')->assertOk();
        $this->assertSame(0, SecurityEvent::where('reason', 'honeypot')->count());
    }

    public function test_a_signed_in_user_is_never_trapped(): void
    {
        $this->actingAs(User::where('username', 'staffmrp')->firstOrFail());

        $this->get('/admin')->assertOk()->assertDontSee('Amatiran');
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

    public function test_guests_sent_to_sign_in_are_never_pointed_at_superadmin(): void
    {
        config(['auth.honeypot_trap' => false]);

        $this->withCookie('portal', 'admin')->get('/staff/security')->assertRedirect('/staff');
        $this->get('/staff/orders')->assertRedirect('/staff');
    }

    public function test_the_flag_turns_the_trap_off(): void
    {
        config(['auth.honeypot_trap' => false]);

        $this->get('/admin')->assertNotFound();
        $this->get('/administrator')->assertNotFound();
        $this->get('/menu')->assertOk();
        $this->assertSame(0, $this->heldSince());
    }
}
