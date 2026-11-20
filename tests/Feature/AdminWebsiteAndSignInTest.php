<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\SecurityEvent;
use App\Models\SignIn;
use App\Models\User;
use Database\Seeders\ComponentSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWebsiteAndSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(UserSeeder::class);
    }

    private function admin(): User
    {
        return User::where('role', User::ROLE_ADMIN)->firstOrFail();
    }

    public function test_every_sign_in_is_recorded_and_listed_for_the_superadmin(): void
    {
        $this->post('/staff', ['username' => 'staffmrp', 'password' => '12345678'], ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36'])->assertRedirect();
        auth()->logout();
        $this->post('/superadmin', ['username' => 'superadmin', 'password' => 'brread-admin-2026'])->assertRedirect();

        $this->assertSame(2, SignIn::count());
        $staff = SignIn::where('username', 'staffmrp')->firstOrFail();
        $this->assertSame('staff', $staff->portal);
        $this->assertSame(User::ROLE_WAREHOUSE, $staff->role);
        $this->assertSame('127.0.0.1', $staff->ip);
        $this->assertSame('superadmin', SignIn::where('username', 'superadmin')->firstOrFail()->portal);

        $this->actingAs($this->admin())->get('/staff/security?tab=sign-ins')
            ->assertOk()
            ->assertSee('@staffmrp')
            ->assertSee('@superadmin')
            ->assertSee('Chrome');

        // a wrong password is not a sign-in
        auth()->logout();
        $this->post('/staff', ['username' => 'staffhr', 'password' => 'wrong']);
        $this->assertSame(2, SignIn::count());
    }

    public function test_security_and_website_are_for_the_superadmin_only(): void
    {
        foreach ([User::ROLE_WAREHOUSE, User::ROLE_HR, User::ROLE_ACCOUNTANT] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/staff/security')->assertForbidden();
            $this->actingAs($user)->get('/staff/security?tab=sign-ins')->assertForbidden();
            $this->actingAs($user)->get('/staff/site')->assertForbidden();
            $this->actingAs($user)->put('/staff/site/shop/orders', ['orders_open' => '0'])->assertForbidden();
        }

        auth()->logout();
        $this->get('/staff/security')->assertRedirect();
        $this->get('/staff/site')->assertRedirect();
    }

    public function test_the_honeypot_filter_has_its_new_name(): void
    {
        $this->assertSame('Orang Bego yang Kejebak', SecurityEvent::REASONS['honeypot']);
        $this->actingAs($this->admin())->get('/staff/security')->assertOk()->assertSee('Orang Bego yang Kejebak');
    }

    public function test_hours_address_and_promises_come_from_the_panel(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put('/staff/site/shop/hours', ['hours_open' => '10:00', 'hours_close' => '22:00'])->assertRedirect();
        $this->actingAs($admin)->put('/staff/site/shop/location', [
            'address_short' => 'Blok M Square',
            'address_lines' => "Blok M Square Lt. 2\nJakarta Selatan",
            'maps_query' => 'Blok M Square Jakarta',
        ])->assertRedirect();
        $this->actingAs($admin)->put('/staff/site/shop/promises', ['delivery_minutes' => 35, 'frozen_minutes' => 90, 'promo_text' => 'and get a free topping.'])->assertRedirect();
        auth()->logout();

        $this->get('/contact')
            ->assertOk()
            ->assertSee('Blok M Square Lt. 2', false)
            ->assertSee('opens: 600', false)
            ->assertSee('closes: 1320', false)
            ->assertSee('10:00')
            ->assertSee('about 35 minutes')
            ->assertSee(urlencode('Blok M Square Jakarta'), false);
        $this->get('/')->assertOk()->assertSee('About 35 minutes')->assertSee('90 minutes in the box')->assertSee('and get a free topping.');
        $this->get('/about')->assertOk()->assertSee('Blok M Square')->assertSee('Daily, 10:00 AM to 10:00 PM');
    }

    public function test_closing_time_must_follow_opening_time_and_a_closed_counter_needs_a_note(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->from('/staff/site')->put('/staff/site/shop/hours', ['hours_open' => '22:00', 'hours_close' => '10:00'])->assertSessionHasErrors('hours_close');
        $this->actingAs($admin)->from('/staff/site')->put('/staff/site/shop/hours', ['hours_open' => '10:00', 'hours_close' => '22:00', 'counter_closed' => '1'])->assertSessionHasErrors('counter_closed_note');

        $this->actingAs($admin)->put('/staff/site/shop/hours', ['hours_open' => '10:00', 'hours_close' => '22:00', 'counter_closed' => '1', 'counter_closed_note' => 'back after Lebaran']);
        auth()->logout();
        $this->get('/contact')->assertSee('shut: true', false)->assertSee('back after Lebaran');
    }

    public function test_pausing_orders_stops_checkout_and_tells_the_customer(): void
    {
        $this->seed(ComponentSeeder::class);
        $this->actingAs($this->admin())->put('/staff/site/shop/orders', ['orders_paused_message' => 'Freezer rusak, buka lagi besok.'])->assertRedirect();
        auth()->logout();

        $bread = Component::where('category', 'bread')->firstOrFail();
        $flavor = Component::where('category', 'ice_cream')->firstOrFail();
        $this->postJson('/api/orders', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'items' => [['component_ids' => [$bread->id, $flavor->id], 'quantity' => 1]],
        ])->assertStatus(503)->assertJson(['message' => 'Freezer rusak, buka lagi besok.']);

        $this->get('/')->assertSee('Freezer rusak, buka lagi besok.');

        // and back on
        $this->actingAs($this->admin())->put('/staff/site/shop/orders', ['orders_open' => '1', 'orders_paused_message' => 'x'])->assertRedirect();
        auth()->logout();
        $this->postJson('/api/orders', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'items' => [['component_ids' => [$bread->id, $flavor->id], 'quantity' => 1]],
        ])->assertCreated();
    }
}
