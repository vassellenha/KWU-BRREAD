<?php

namespace Tests\Feature;

use App\Http\Middleware\StartSessionForTrustedIp;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperadminSessionTest extends TestCase
{
    use RefreshDatabase;

    private const TRUSTED = '103.171.164.172';

    private array $credentials = ['username' => 'superadmin', 'password' => 'brread-admin-2026'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(UserSeeder::class);
    }

    public function test_guests_of_staff_pages_go_to_the_right_sign_in_and_come_back_afterwards(): void
    {
        $this->get('/staff/mrp')->assertRedirect('/staff');
        $this->withCookie('portal', 'admin')->get('/staff/mrp')->assertRedirect('/superadmin');

        // The page the guest asked for is opened after the superadmin signs in.
        $this->withSession(['url.intended' => url('/staff/mrp')])->post('/superadmin', $this->credentials)->assertRedirect('/staff/mrp');
    }

    public function test_an_outside_address_is_never_followed_after_signing_in(): void
    {
        $home = route(User::where('role', User::ROLE_ADMIN)->firstOrFail()->homeRouteName());

        foreach (['https://evil.example/staff/mrp', '//evil.example/staff/mrp', '/staff/../etc', 'javascript:alert(1)', '/other'] as $bad) {
            $this->withSession(['url.intended' => $bad])
                ->post('/superadmin', $this->credentials)
                ->assertRedirect($home);

            $this->post(route('staff.logout'));
        }
    }

    public function test_staff_accounts_still_start_at_their_own_home(): void
    {
        $accountant = User::where('username', 'staffacc')->firstOrFail();

        $this->withSession(['url.intended' => url('/staff/hr')])
            ->post('/staff', ['username' => 'staffacc', 'password' => '12345678'])
            ->assertRedirect(route($accountant->homeRouteName()));
    }

    public function test_the_trusted_address_config_is_read_from_the_environment_setting(): void
    {
        $this->assertSame([self::TRUSTED], config('auth.superadmin_trusted_ips'));
        $this->assertSame(30, config('auth.superadmin_session_days'));
    }

    public function test_a_superadmin_browser_on_a_trusted_address_gets_the_long_session(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => self::TRUSTED])->withCookie('portal', 'admin')->get('/superadmin');

        $this->assertSame(30 * 24 * 60, (int) config('session.lifetime'));
    }

    public function test_other_addresses_and_other_browsers_keep_the_normal_lifetime(): void
    {
        $normal = (int) config('session.lifetime');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->withCookie('portal', 'admin')->get('/superadmin');
        $this->assertSame($normal, (int) config('session.lifetime'));

        $this->withServerVariables(['REMOTE_ADDR' => self::TRUSTED])->get('/staff');
        $this->assertSame($normal, (int) config('session.lifetime'));
        $this->assertGreaterThan($normal, StartSessionForTrustedIp::longLifetimeMinutes());
    }

    public function test_the_heartbeat_needs_a_superadmin_and_answers_204(): void
    {
        $this->getJson(route('staff.ping'))->assertUnauthorized();

        $staff = User::where('role', '!=', User::ROLE_ADMIN)->firstOrFail();
        $this->actingAs($staff)->getJson(route('staff.ping'))->assertForbidden();

        $admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $this->actingAs($admin)->getJson(route('staff.ping'))->assertNoContent();
    }

    public function test_the_music_and_its_button_only_exist_on_customer_pages(): void
    {
        $this->get('/')->assertOk()->assertSee('data-customer-site', false)->assertSee('data-bgm-toggle', false);

        foreach (['/staff', '/superadmin'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('data-customer-site', false)->assertDontSee('data-bgm-toggle', false);
        }

        $admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $this->actingAs($admin)->get(route('staff.orders.index'))
            ->assertOk()
            ->assertDontSee('data-customer-site', false)
            ->assertDontSee('data-bgm-toggle', false)
            ->assertSee('data-heartbeat-url', false);
    }
}
