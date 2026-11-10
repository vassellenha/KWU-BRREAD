<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\ComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderWorkflowAndReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
    }

    private function createOrder(): Order
    {
        $bread = Component::where('category', 'bread')->firstOrFail();
        $flavor = Component::where('category', 'ice_cream')->firstOrFail();

        $response = $this->postJson('/api/orders', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'items' => [
                [
                    'component_ids' => [$bread->id, $flavor->id],
                    'quantity' => 2,
                ],
            ],
        ]);

        $response->assertCreated();

        return Order::where('order_number', $response->json('order_number'))->firstOrFail();
    }

    public function test_order_appears_in_staff_orders_panel(): void
    {
        $order = $this->createOrder();

        $response = $this->actingAs($this->admin)->get('/staff/orders');

        $response->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Budi Santoso')
            ->assertSee('Pending');
    }

    public function test_staff_can_confirm_order_and_generate_public_tracking_link(): void
    {
        $order = $this->createOrder();
        $this->assertNull($order->confirmation_token);
        $this->assertSame(Order::STATUS_PENDING, $order->status);

        $response = $this->actingAs($this->admin)
            ->post("/staff/orders/{$order->id}/confirm");

        $response->assertRedirect();
        $order->refresh();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->status);
        $this->assertNotNull($order->confirmation_token);
        $this->assertNotNull($order->confirmed_at);
        $this->assertNotEmpty($order->public_status_url);
    }

    public function test_public_can_view_order_status_and_receipt_without_login(): void
    {
        $order = $this->createOrder();
        $this->actingAs($this->admin)->post("/staff/orders/{$order->id}/confirm");
        $order->refresh();

        $response = $this->get("/order/status/{$order->confirmation_token}");

        $response->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Budi Santoso')
            ->assertSee('Pesanan Terkonfirmasi')
            ->assertSee('Rincian Belanja')
            ->assertSee('Ulasan & Rating Pesanan');
    }

    public function test_public_customer_can_submit_review_with_rating_and_comment(): void
    {
        $order = $this->createOrder();
        $this->actingAs($this->admin)->post("/staff/orders/{$order->id}/confirm");
        $order->refresh();

        $response = $this->post("/order/status/{$order->confirmation_token}/review", [
            'rating' => 5,
            'review_text' => 'Sandwich es krim terenak di Jakarta! Roti empuk dan dingin mantap.',
        ]);

        $response->assertRedirect();
        $order->refresh();

        $this->assertSame(5, $order->rating);
        $this->assertSame('Sandwich es krim terenak di Jakarta! Roti empuk dan dingin mantap.', $order->review_text);
        $this->assertNotNull($order->reviewed_at);

        // Check it displays on public status page
        $page = $this->get("/order/status/{$order->confirmation_token}");
        $page->assertOk()
            ->assertSee('5 / 5 Bintang')
            ->assertSee('Sandwich es krim terenak di Jakarta!');
    }

    public function test_staff_can_cancel_order_and_stock_is_restored(): void
    {
        $bread = Component::where('category', 'bread')->firstOrFail();
        $flavor = Component::where('category', 'ice_cream')->firstOrFail();
        $initialBreadStock = $bread->stock_quantity;
        $initialFlavorStock = $flavor->stock_quantity;

        // Place order for 3 sandwiches
        $response = $this->postJson('/api/orders', [
            'customer_name' => 'Siti Nurhaliza',
            'customer_phone' => '08987654321',
            'items' => [
                [
                    'component_ids' => [$bread->id, $flavor->id],
                    'quantity' => 3,
                ],
            ],
        ]);
        $response->assertCreated();
        $order = Order::where('order_number', $response->json('order_number'))->firstOrFail();

        // Verify stock dropped by 3
        $this->assertSame($initialBreadStock - 3, $bread->fresh()->stock_quantity);
        $this->assertSame($initialFlavorStock - 3, $flavor->fresh()->stock_quantity);

        // Cancel order
        $cancelResponse = $this->actingAs($this->admin)->post("/staff/orders/{$order->id}/cancel");
        $cancelResponse->assertRedirect();
        $order->refresh();

        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertNotNull($order->cancelled_at);

        // Verify stock is restored
        $this->assertSame($initialBreadStock, $bread->fresh()->stock_quantity);
        $this->assertSame($initialFlavorStock, $flavor->fresh()->stock_quantity);

        $this->assertDatabaseHas('stock_movements', [
            'order_id' => $order->id,
            'type' => StockMovement::TYPE_ADJUSTMENT,
            'quantity_change' => 3,
        ]);
    }
}

