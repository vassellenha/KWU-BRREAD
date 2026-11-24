<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\ComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutWhatsappTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);
    }

    public function test_the_cart_hands_the_shop_whatsapp_link_to_the_checkout(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->put('/staff/site/contact', [
            'contact_name' => 'Kak Rizky',
            'contact_phone' => '0812 3456 7890',
            'contact_whatsapp' => '0812 3456 7890',
            'instagram_handle' => '@brread.jkt',
        ])->assertRedirect();
        auth()->logout();

        $this->get('/menu')->assertOk()->assertSee('data-wa-url="https://wa.me/6281234567890"', false);
    }

    public function test_without_a_whatsapp_number_the_checkout_has_no_link(): void
    {
        SiteSetting::put(['contact_whatsapp' => '']);

        $this->get('/menu')->assertOk()->assertSee('data-wa-url=""', false);
    }

    public function test_the_order_still_goes_through_and_returns_what_the_message_needs(): void
    {
        $bread = Component::where('category', 'bread')->firstOrFail();
        $flavor = Component::where('category', 'ice_cream')->firstOrFail();

        $this->postJson('/api/orders', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'items' => [['component_ids' => [$bread->id, $flavor->id], 'quantity' => 1]],
        ])->assertCreated()->assertJsonStructure(['order_number', 'total_price']);
    }

    public function test_several_admin_numbers_are_all_handed_to_the_checkout(): void
    {
        SiteSetting::put(['contact_whatsapp' => 'Atmint Pasel: +62 821-3601-9367, Atmint Sandi: +62 811-6362-805']);

        $this->get('/menu')->assertOk()
            ->assertSee('data-wa-url="https://wa.me/6282136019367"', false)
            ->assertSee('Atmint Pasel', false)
            ->assertSee('Atmint Sandi', false)
            ->assertSee('https:\/\/wa.me\/628116362805', false);
    }

    public function test_the_contact_field_accepts_up_to_three_numbers_and_rejects_junk(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $payload = ['contact_name' => 'x', 'contact_phone' => '0812 3456 7890', 'instagram_handle' => 'brread'];

        $this->actingAs($admin)->put('/staff/site/contact', $payload + ['contact_whatsapp' => 'Atmint Pasel: 0821 3601 9367, Atmint Sandi: 0811 6362 805'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->put('/staff/site/contact', $payload + ['contact_whatsapp' => '0811111111, 0822222222, 0833333333, 0844444444'])
            ->assertSessionHasErrors('contact_whatsapp');
        $this->actingAs($admin)->put('/staff/site/contact', $payload + ['contact_whatsapp' => '0811111111, abc'])
            ->assertSessionHasErrors('contact_whatsapp');
    }

    public function test_each_admin_can_have_a_message_style(): void
    {
        SiteSetting::put(['contact_whatsapp' => 'Atmint Pasel: 0821 3601 9367 (ceria), Atmint Sandi: 0811 6362 805 (formal)']);

        $this->get('/menu')->assertOk()
            ->assertSee('&quot;style&quot;:&quot;ceria&quot;', false)
            ->assertSee('"style":"formal"', false);
    }

    public function test_an_unknown_message_style_is_refused(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $payload = ['contact_name' => 'x', 'contact_phone' => '0812 3456 7890', 'instagram_handle' => 'brread'];

        $this->actingAs($admin)->put('/staff/site/contact', $payload + ['contact_whatsapp' => 'Atmint Pasel: 0821 3601 9367 (galak)'])
            ->assertSessionHasErrors('contact_whatsapp');
    }
}
