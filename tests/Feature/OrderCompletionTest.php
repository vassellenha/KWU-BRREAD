<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Order;
use App\Models\User;
use App\Support\ReviewFeed;
use Database\Seeders\ComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** The "Selesai" step: staff can close an order, and the customer can once a rating and a comment are in. */
class OrderCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);
    }

    private function order(string $status = Order::STATUS_READY, string $name = 'Budi Santoso'): Order
    {
        $bread = Component::where('category', 'bread')->firstOrFail();
        $flavor = Component::where('category', 'ice_cream')->firstOrFail();

        $number = $this->postJson('/api/orders', [
            'customer_name' => $name,
            'customer_phone' => '081200000009',
            'items' => [['component_ids' => [$bread->id, $flavor->id], 'quantity' => 1]],
        ])->assertCreated()->json('order_number');

        $order = Order::where('order_number', $number)->firstOrFail();
        $order->forceFill(['status' => $status, 'confirmation_token' => Str::random(40), 'ready_at' => now()])->save();

        return $order->fresh();
    }

    private function url(Order $order, string $path = ''): string
    {
        return "/order/status/{$order->confirmation_token}{$path}";
    }

    public function test_staff_mark_a_ready_order_as_done(): void
    {
        $order = $this->order();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post(route('staff.orders.completed', $order))->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
        $this->assertNotNull($order->completed_at);
    }

    public function test_staff_cannot_finish_an_order_that_is_not_ready_yet(): void
    {
        $order = $this->order(Order::STATUS_PROCESSING);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post(route('staff.orders.completed', $order))->assertSessionHas('error');

        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
    }

    public function test_a_done_order_has_its_own_tab_and_cannot_be_cancelled(): void
    {
        $order = $this->order(Order::STATUS_COMPLETED);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get(route('staff.orders.index', ['status' => 'completed']))
            ->assertOk()
            ->assertSee($order->order_number);

        $this->actingAs($admin)->post(route('staff.orders.cancel', $order))->assertSessionHas('error');
        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
    }

    public function test_the_customer_cannot_finish_without_a_review(): void
    {
        $order = $this->order();

        $this->post($this->url($order, '/complete'))->assertSessionHas('error');

        $this->assertSame(Order::STATUS_READY, $order->fresh()->status);
    }

    public function test_the_customer_finishes_after_leaving_stars_and_a_comment(): void
    {
        $order = $this->order();
        $order->update(['rating' => 5, 'review_text' => 'Enak banget, esnya masih beku.', 'reviewed_at' => now()]);

        $this->post($this->url($order, '/complete'))->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
        $this->assertNotNull($order->completed_at);
    }

    public function test_stars_without_words_are_not_enough_but_the_comment_can_be_added_while_finishing(): void
    {
        $order = $this->order();
        $order->update(['rating' => 4, 'reviewed_at' => now()]);

        $this->post($this->url($order, '/complete'))->assertSessionHasErrors('review_text');
        $this->assertSame(Order::STATUS_READY, $order->fresh()->status);

        $this->post($this->url($order, '/complete'), ['review_text' => '  Manisnya pas  '])->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
        $this->assertSame('Manisnya pas', $order->review_text);
    }

    public function test_the_review_form_can_review_and_finish_in_one_go_but_needs_a_comment(): void
    {
        $order = $this->order();

        $this->postJson($this->url($order, '/review'), ['rating' => 5, 'finish' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('review_text');
        $this->assertNull($order->fresh()->rating);

        $this->postJson($this->url($order, '/review'), ['rating' => 5, 'review_text' => 'Mantap sekali!', 'finish' => 1])
            ->assertOk()
            ->assertJson(['completed' => true]);

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
    }

    public function test_finishing_through_the_review_needs_a_ready_order(): void
    {
        $order = $this->order(Order::STATUS_PAID);

        $this->postJson($this->url($order, '/review'), ['rating' => 5, 'review_text' => 'Belum diambil', 'finish' => 1])
            ->assertStatus(409);

        $this->assertNull($order->fresh()->rating);
    }

    public function test_a_review_without_finishing_still_works_with_stars_only(): void
    {
        $order = $this->order(Order::STATUS_CONFIRMED);

        $this->postJson($this->url($order, '/review'), ['rating' => 4])->assertOk();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
    }

    public function test_signed_in_staff_cannot_finish_as_the_customer(): void
    {
        $order = $this->order();
        $order->update(['rating' => 5, 'review_text' => 'Enak', 'reviewed_at' => now()]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post($this->url($order, '/complete'))->assertSessionHas('error');

        $this->assertSame(Order::STATUS_READY, $order->fresh()->status);
    }

    public function test_the_tracking_page_shows_the_right_controls(): void
    {
        $order = $this->order();
        $this->get($this->url($order))->assertOk()->assertSee('Kirim Ulasan &amp; Selesaikan Pesanan', false);

        $order->update(['rating' => 5, 'review_text' => 'Enak banget', 'reviewed_at' => now()]);
        $this->get($this->url($order))->assertOk()->assertSee('Selesaikan Pesanan')->assertDontSee('Kirim Ulasan');

        $order->update(['status' => Order::STATUS_COMPLETED, 'completed_at' => now()]);
        $this->get($this->url($order))->assertOk()->assertSee('Pesanan Selesai')->assertDontSee('Selesaikan Pesanan');
    }

    public function test_reviews_of_finished_orders_feed_the_home_page_photos_first_and_words_below(): void
    {
        $words = $this->order(Order::STATUS_COMPLETED, 'Komang Aditya');
        $words->update(['rating' => 5, 'review_text' => 'Teksnya doang tapi enak banget', 'reviewed_at' => now()]);

        $this->assertSame(['Komang A.'], ReviewFeed::words()->pluck('name')->all());
        $this->assertCount(0, ReviewFeed::photos());
    }

    public function test_the_migration_closes_the_orders_of_irgi_and_fadking_only(): void
    {
        $irgi = $this->order(Order::STATUS_READY, 'Irgi Pratama');
        $fadking = $this->order(Order::STATUS_READY, 'Fadking');
        $other = $this->order(Order::STATUS_READY, 'Hanif');
        DB::table('orders')->whereIn('id', [$irgi->id, $fadking->id, $other->id])->update(['completed_at' => null]);

        $migration = require base_path('database/migrations/2026_11_26_180000_add_completed_status_to_orders.php');
        $migration->down();
        $migration->up();

        $this->assertSame(Order::STATUS_COMPLETED, $irgi->fresh()->status);
        $this->assertSame(Order::STATUS_COMPLETED, $fadking->fresh()->status);
        $this->assertSame(Order::STATUS_READY, $other->fresh()->status);
    }
}
