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
}
