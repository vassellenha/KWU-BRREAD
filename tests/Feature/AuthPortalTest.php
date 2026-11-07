<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(UserSeeder::class);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function staffAccounts(): array
    {
        return [
            'mrp' => ['staffmrp', '/staff/mrp'],
            'hr' => ['staffhr', '/staff/hr'],
            'accounting' => ['staffacc', '/staff/accounting'],
        ];
    }

    #[DataProvider('staffAccounts')]
    public function test_staff_sign_in_at_staff_and_land_on_their_own_module(string $username, string $home): void
    {
        $this->post('/staff', ['username' => $username, 'password' => '12345678'])
            ->assertRedirect($home);

        $this->assertAuthenticated();
        $this->get($home)->assertOk();
    }

    public function test_usernames_ignore_the_capital_a_phone_keyboard_adds(): void
    {
        $this->post('/staff', ['username' => 'Staffmrp', 'password' => '12345678'])
            ->assertRedirect('/staff/mrp');

        $this->assertAuthenticated();
    }

    public function test_the_staff_portal_suggests_nothing(): void
    {
        $this->get('/staff')
            ->assertOk()
            ->assertDontSee('Quick login')
            ->assertDontSee('staffmrp')
            ->assertDontSee('12345678')
            ->assertDontSee('admin');
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $this->from('/staff')->post('/staff', ['username' => 'staffmrp', 'password' => 'wrong'])
            ->assertRedirect('/staff')
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_an_admin_cannot_sign_in_through_the_staff_portal(): void
    {
        $this->from('/staff')->post('/staff', ['username' => 'admin', 'password' => 'brread-admin-2026'])
            ->assertRedirect('/staff')
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_staff_cannot_sign_in_through_the_admin_portal(): void
    {
        $this->from('/admin')->post('/admin', ['username' => 'staffhr', 'password' => '12345678'])
            ->assertRedirect('/admin')
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_an_admin_signs_in_at_admin(): void
    {
        $this->post('/admin', ['username' => 'admin', 'password' => 'brread-admin-2026'])
            ->assertRedirect('/staff/mrp');

        $this->assertAuthenticated();
    }

    public function test_the_admin_portal_suggests_quick_login_without_exposing_the_password(): void
    {
        config(['auth.admin_quick_login' => true]);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Quick login')
            ->assertDontSee('brread-admin-2026');
    }

    public function test_quick_login_signs_in_as_the_admin(): void
    {
        config(['auth.admin_quick_login' => true]);

        $this->post('/admin/quick-login')->assertRedirect('/staff/mrp');

        $this->assertAuthenticatedAs(User::where('username', 'admin')->first());
    }

    public function test_quick_login_is_unavailable_when_switched_off(): void
    {
        config(['auth.admin_quick_login' => false]);

        $this->get('/admin')->assertOk()->assertDontSee('Quick login');
        $this->post('/admin/quick-login')->assertNotFound();

        $this->assertGuest();
    }

    public function test_guests_are_sent_to_the_staff_portal(): void
    {
        $this->get('/staff/mrp')->assertRedirect('/staff');
        $this->get('/staff/hr')->assertRedirect('/staff');
        $this->get('/staff/accounting')->assertRedirect('/staff');
    }

    public function test_the_old_login_url_redirects_to_the_staff_portal(): void
    {
        $this->get('/staff/login')->assertRedirect('/staff');
    }

    public function test_a_saved_deep_link_to_a_forbidden_module_does_not_greet_a_login_with_403(): void
    {
        $this->get('/staff/accounting')->assertRedirect('/staff');

        $this->post('/staff', ['username' => 'staffmrp', 'password' => '12345678'])
            ->assertRedirect('/staff/mrp');
    }

    #[DataProvider('staffAccounts')]
    public function test_a_signed_in_user_opening_a_portal_goes_to_their_module(string $username, string $home): void
    {
        $user = User::where('username', $username)->first();

        $this->actingAs($user)->get('/staff')->assertRedirect($home);
        $this->actingAs($user)->get('/admin')->assertRedirect($home);
    }

    public function test_logging_out_returns_to_the_portal_the_account_belongs_to(): void
    {
        $this->actingAs(User::where('username', 'staffhr')->first())
            ->post('/staff/logout')
            ->assertRedirect('/staff');

        $this->actingAs(User::where('username', 'admin')->first())
            ->post('/staff/logout')
            ->assertRedirect('/admin');
    }

    public function test_repeated_failed_logins_are_throttled(): void
    {
        foreach (range(1, 10) as $attempt) {
            $this->from('/staff')->post('/staff', ['username' => 'staffmrp', 'password' => 'wrong']);
        }

        $this->from('/staff')->post('/staff', ['username' => 'staffmrp', 'password' => '12345678'])
            ->assertRedirect('/staff')
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }
}
