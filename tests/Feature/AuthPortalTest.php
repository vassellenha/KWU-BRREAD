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
            'mrp' => ['staffmrp', '/staff/orders'],
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
            ->assertRedirect('/staff/orders');

        $this->assertAuthenticated();
    }

    public function test_the_staff_portal_suggests_nothing(): void
    {
        $this->get('/staff')
            ->assertOk()
            ->assertDontSee('Quick login')
            ->assertDontSee('staffmrp')
            ->assertDontSee('12345678')
            ->assertDontSee('superadmin');
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
        $this->from('/staff')->post('/staff', ['username' => 'superadmin', 'password' => 'brread-admin-2026'])
            ->assertRedirect('/staff')
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_staff_cannot_sign_in_through_the_admin_portal(): void
    {
        $this->from('/superadmin')->post('/superadmin', ['username' => 'staffhr', 'password' => '12345678'])
            ->assertRedirect('/superadmin')
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_an_admin_signs_in_at_admin(): void
    {
        $this->post('/superadmin', ['username' => 'superadmin', 'password' => 'brread-admin-2026'])
            ->assertRedirect('/staff/orders');

        $this->assertAuthenticated();
    }

    public function test_the_admin_portal_offers_quick_login_without_printing_the_password(): void
    {
        config(['auth.admin_quick_login' => true]);

        $this->get('/superadmin')
            ->assertOk()
            ->assertSee('Quick login')
            ->assertDontSee('brread-admin-2026');
    }

    public function test_the_admin_portal_never_exposes_the_password_when_quick_login_is_off(): void
    {
        config(['auth.admin_quick_login' => false]);

        $this->get('/superadmin')->assertOk()->assertDontSee('brread-admin-2026');
        $this->get('/staff')->assertOk()->assertDontSee('brread-admin-2026');
    }

    public function test_quick_login_signs_in_as_the_admin(): void
    {
        config(['auth.admin_quick_login' => true]);

        $this->post('/superadmin/quick-login')->assertRedirect('/staff/orders');

        $this->assertAuthenticatedAs(User::where('username', 'superadmin')->first());
    }

    public function test_quick_login_is_unavailable_when_switched_off(): void
    {
        config(['auth.admin_quick_login' => false]);

        $this->get('/superadmin')->assertOk()->assertDontSee('Quick login');
        $this->post('/superadmin/quick-login')->assertNotFound();

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
            ->assertRedirect('/staff/orders');
    }

    #[DataProvider('staffAccounts')]
    public function test_a_signed_in_user_opening_a_portal_goes_to_their_module(string $username, string $home): void
    {
        $user = User::where('username', $username)->first();

        $this->actingAs($user)->get('/staff')->assertRedirect($home);
        $this->actingAs($user)->get('/superadmin')->assertRedirect($home);
    }

    public function test_logging_out_returns_to_the_portal_the_account_belongs_to(): void
    {
        $this->actingAs(User::where('username', 'staffhr')->first())
            ->post('/staff/logout')
            ->assertRedirect('/staff');

        $this->actingAs(User::where('username', 'superadmin')->first())
            ->post('/staff/logout')
            ->assertRedirect('/superadmin');
    }

    public function test_the_login_forms_show_a_captcha_image_when_it_is_on(): void
    {
        config(['auth.login_captcha' => true]);

        $this->get('/staff')->assertOk()->assertSee('Type the letters above')->assertSee('/captcha?v=', false);
        $this->get('/superadmin')->assertOk()->assertSee('Type the letters above');
    }

    public function test_the_login_forms_have_no_captcha_when_it_is_off(): void
    {
        config(['auth.login_captcha' => false]);

        $this->get('/staff')->assertOk()->assertDontSee('Type the letters above');
        $this->get('/captcha')->assertNotFound();
    }

    public function test_the_captcha_image_is_a_png_that_is_never_cached_and_keeps_its_code_in_the_session(): void
    {
        config(['auth.login_captcha' => true]);

        $response = $this->get('/captcha')->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith("\x89PNG", $response->getContent());
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{5}$/', session('login_captcha_answer'));
        $this->assertStringNotContainsString(session('login_captcha_answer'), $response->getContent());
    }

    public function test_a_login_without_the_captcha_text_is_refused(): void
    {
        config(['auth.login_captcha' => true]);

        $this->get('/captcha');
        $this->from('/staff')->post('/staff', ['username' => 'staffmrp', 'password' => '12345678'])
            ->assertRedirect('/staff')
            ->assertSessionHasErrors('captcha');

        $this->assertGuest();
    }

    public function test_a_wrong_captcha_text_is_refused_even_with_the_right_password(): void
    {
        config(['auth.login_captcha' => true]);

        $this->get('/captcha');

        $this->from('/staff')->post('/staff', ['username' => 'staffmrp', 'password' => '12345678', 'captcha' => 'AAAAA'])
            ->assertSessionHasErrors('captcha');

        $this->assertGuest();
    }

    public function test_a_captcha_text_cannot_be_replayed(): void
    {
        config(['auth.login_captcha' => true]);

        $this->get('/captcha');
        $answer = session('login_captcha_answer');

        // The first attempt uses the code up, even though the password is wrong.
        $this->from('/staff')->post('/staff', ['username' => 'staffmrp', 'password' => 'wrong', 'captcha' => $answer])
            ->assertSessionHasErrors('username');

        // Same text again, now with the right password: the old code is gone.
        $this->from('/staff')->post('/staff', ['username' => 'staffmrp', 'password' => '12345678', 'captcha' => $answer])
            ->assertSessionHasErrors('captcha');

        $this->assertGuest();
    }

    public function test_a_staff_login_succeeds_with_the_right_captcha_text_in_any_case(): void
    {
        config(['auth.login_captcha' => true]);

        $this->get('/captcha');
        $answer = strtolower(session('login_captcha_answer'));

        $this->post('/staff', ['username' => 'staffmrp', 'password' => '12345678', 'captcha' => ' '.$answer.' '])
            ->assertRedirect('/staff/orders');

        $this->assertAuthenticated();
    }

    public function test_repeated_failed_logins_are_throttled(): void
    {
        foreach (range(1, 10) as $attempt) {
            $this->from('/staff')->post('/staff', ['username' => 'staffmrp', 'password' => 'wrong']);
        }

        $this->from('/staff')->post('/staff', ['username' => 'staffmrp', 'password' => '12345678'])
            ->assertStatus(429)
            ->assertSee('SIGN-IN HALTED');

        $this->assertGuest();
    }

    public function test_the_superadmin_login_lives_at_its_own_address_and_the_old_one_redirects(): void
    {
        $this->get('/admin')->assertStatus(301)->assertRedirect('/superadmin');

        $this->get('/superadmin')
            ->assertOk()
            ->assertSee('Superadmin')
            ->assertSee('behind the counter')
            ->assertDontSee('Admin Portal')
            ->assertDontSee('Full access to every module');
    }

    public function test_the_old_admin_account_is_renamed_to_superadmin_and_keeps_its_password(): void
    {
        $user = User::where('username', 'superadmin')->firstOrFail();
        $user->forceFill(['username' => 'admin'])->save();

        (require base_path('database/migrations/2026_11_16_100000_rename_admin_account_to_superadmin.php'))->up();

        $renamed = $user->fresh();
        $this->assertSame('superadmin', $renamed->username);

        $this->post('/superadmin', ['username' => 'superadmin', 'password' => 'brread-admin-2026'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($renamed);
    }
}
