<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Order;
use App\Support\Code39;
use Database\Seeders\ComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

/**
 * The animated order status page, its print-only receipt, the Code 39 barcode and the
 * background review post that feeds the thank-you popup.
 */
class OrderStatusExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);
    }

    private function orderWith(array $names, string $status = Order::STATUS_CONFIRMED): Order
    {
        $ids = Component::whereIn('name', $names)->pluck('id')->all();

        $number = $this->postJson('/api/orders', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'items' => [['component_ids' => $ids, 'quantity' => 2]],
        ])->assertCreated()->json('order_number');

        $order = Order::where('order_number', $number)->firstOrFail();
        $order->forceFill([
            'status' => $status,
            'confirmation_token' => Str::random(40),
            'confirmed_at' => $status === Order::STATUS_CONFIRMED ? now() : null,
        ])->save();

        return $order->fresh();
    }

    /** How Blade's Js::from escapes a double quote inside an attribute (a backslash, then u0022). */
    private function jsQuote(): string
    {
        return chr(92).'u0022';
    }

    private function statusUrl(Order $order): string
    {
        return "/order/status/{$order->confirmation_token}";
    }

    public function test_confirmed_order_renders_the_animated_hero_and_tracker(): void
    {
        $order = $this->orderWith(['Plain White Bread', 'Strawberry', 'Rainbow Sprinkles']);

        $this->get($this->statusUrl($order))
            ->assertOk()
            ->assertSee('os-hero--confirmed', false)
            ->assertSee('1. Masuk')
            ->assertSee('2. Dikonfirmasi')
            ->assertSee('3. Dibayar')
            ->assertSee('4. Siap Nikmati')
            // confirmed but not paid yet: the payment stamp waits, the last one is still empty
            ->assertSee('Menunggu bayar')
            ->assertDontSee('os-step is-ready', false)
            ->assertSee('orderTracker', false);
    }

    public function test_a_paid_order_stamps_the_payment_and_is_ready_to_enjoy(): void
    {
        $order = $this->orderWith(['Plain White Bread', 'Strawberry', 'Rainbow Sprinkles']);
        $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();

        $this->get($this->statusUrl($order))
            ->assertOk()
            ->assertSee('os-hero--confirmed', false)
            ->assertSee('os-step is-ready', false)
            ->assertSee('osi-wallet', false)
            ->assertSee('Pembayaran diterima')
            ->assertSee('Pesanan Sudah Dibayar')
            ->assertDontSee('Menunggu bayar');
    }

    public function test_the_animated_sandwich_uses_art_that_matches_the_ingredients(): void
    {
        $order = $this->orderWith(['Whole Wheat Bread', 'Chocolate', 'Chocolate Sauce']);

        $this->get($this->statusUrl($order))
            ->assertOk()
            ->assertSee('/img/cutout/bread-wheat.webp', false)
            ->assertSee('/img/cutout/ice-chocolate.webp', false)
            ->assertSee('/img/cutout/top-sauce.webp', false)
            ->assertDontSee('/img/cutout/top-sprinkles.webp', false);
    }

    public function test_pending_and_cancelled_orders_get_their_own_hero_state(): void
    {
        $pending = $this->orderWith(['Plain White Bread', 'Vanilla'], Order::STATUS_PENDING);
        $this->get($this->statusUrl($pending))
            ->assertOk()
            ->assertSee('os-hero--pending', false)
            ->assertSee('Menunggu Konfirmasi')
            ->assertSee('os-step is-current', false)
            ->assertSee('os-wait', false);

        $cancelled = $this->orderWith(['Plain White Bread', 'Vanilla'], Order::STATUS_CANCELLED);
        $this->get($this->statusUrl($cancelled))
            ->assertOk()
            ->assertSee('os-hero--cancelled', false)
            ->assertSee('Pesanan Dibatalkan')
            ->assertSee('os-step is-stopped', false)
            ->assertSee('os-puddle', false);
    }

    public function test_the_print_receipt_lists_every_ingredient_with_its_own_price_and_a_barcode(): void
    {
        $order = $this->orderWith(['Plain White Bread', 'Strawberry', 'Rainbow Sprinkles']);

        $response = $this->get($this->statusUrl($order))->assertOk();

        $response->assertSee('class="os-print"', false)
            ->assertSee('rcpt__stamp--confirmed', false)
            ->assertSee('Terkonfirmasi')
            ->assertSeeInOrder(['Roti', 'Plain White Bread', 'Rp 6.000', 'Es Krim', 'Strawberry', 'Rp 8.000', 'Topping', 'Rainbow Sprinkles', 'Rp 3.000'])
            ->assertSee('2 pcs &times; Rp 17.000', false)
            ->assertSee('Rp 34.000')
            ->assertSee('aria-label="Barcode '.Code39::clean($order->order_number).'"', false)
            ->assertSee('&#64;'.config('services.instagram.handle'), false);
    }

    public function test_the_print_receipt_stamp_follows_the_order_status(): void
    {
        $pending = $this->orderWith(['Plain White Bread', 'Vanilla'], Order::STATUS_PENDING);
        $this->get($this->statusUrl($pending))->assertSee('rcpt__stamp--pending', false);

        $cancelled = $this->orderWith(['Plain White Bread', 'Vanilla'], Order::STATUS_CANCELLED);
        $this->get($this->statusUrl($cancelled))->assertSee('rcpt__stamp--cancelled', false);
    }

    public function test_code39_only_keeps_characters_a_barcode_can_carry(): void
    {
        $this->assertSame('BRREAD-AB12', Code39::clean('brread-ab12'));
        $this->assertSame('ABC 1.2-3', Code39::clean('abc 1.2-3'));
        $this->assertSame('AB', Code39::clean("A*B\n#"));
    }

    public function test_every_code39_pattern_has_nine_elements_with_exactly_three_wide(): void
    {
        $patterns = (new ReflectionClass(Code39::class))->getConstant('PATTERNS');

        $this->assertCount(40, $patterns);
        foreach ($patterns as $char => $pattern) {
            $this->assertSame(9, strlen($pattern), "pattern for {$char}");
            $this->assertSame(3, substr_count($pattern, 'w'), "wide elements for {$char}");
        }
    }

    public function test_code39_frames_the_text_with_start_and_stop_characters(): void
    {
        // *A* = 3 characters of 9 elements plus 2 single-module gaps.
        $modules = Code39::modules('A');
        $this->assertCount(3 * 9 + 2, $modules);

        // Both the first and last element are bars, so the code starts and ends on a bar.
        $this->assertSame(0, (count($modules) - 1) % 2);

        // Narrow = 1, wide = 3, and 3 wide per character: 3 chars * (6 narrow + 3 wide) + 2 gaps.
        $this->assertSame(3 * (6 * 1 + 3 * 3) + 2, array_sum($modules));

        $svg = Code39::svg('BRREAD-1');
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('aria-label="Barcode BRREAD-1"', $svg);
        $this->assertStringContainsString('<rect', $svg);
    }

    public function test_a_json_review_returns_the_saved_review_for_the_popup(): void
    {
        $order = $this->orderWith(['Plain White Bread', 'Vanilla']);

        $this->postJson("{$this->statusUrl($order)}/review", ['rating' => 4, 'review_text' => '  Enak banget  '])
            ->assertOk()
            ->assertJson(['rating' => 4, 'review_text' => 'Enak banget']);

        $order->refresh();
        $this->assertSame(4, $order->rating);
        $this->assertNotNull($order->reviewed_at);
    }

    public function test_a_json_review_reports_validation_errors_and_leaves_the_order_unreviewed(): void
    {
        $order = $this->orderWith(['Plain White Bread', 'Vanilla']);

        $this->postJson("{$this->statusUrl($order)}/review", ['rating' => 9])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rating');

        $this->assertFalse($order->fresh()->hasReview());
    }

    public function test_a_json_review_cannot_replace_an_existing_one_or_review_a_cancelled_order(): void
    {
        $order = $this->orderWith(['Plain White Bread', 'Vanilla']);
        $this->postJson("{$this->statusUrl($order)}/review", ['rating' => 5])->assertOk();

        $this->postJson("{$this->statusUrl($order)}/review", ['rating' => 1])->assertStatus(409);
        $this->assertSame(5, $order->fresh()->rating);

        $cancelled = $this->orderWith(['Plain White Bread', 'Vanilla'], Order::STATUS_CANCELLED);
        $this->postJson("{$this->statusUrl($cancelled)}/review", ['rating' => 5])->assertStatus(403);
        $this->assertFalse($cancelled->fresh()->hasReview());
    }

    public function test_the_thank_you_popup_opens_on_load_after_a_plain_form_post(): void
    {
        $order = $this->orderWith(['Plain White Bread', 'Vanilla']);

        $page = $this->followingRedirects()
            ->from($this->statusUrl($order))
            ->post("{$this->statusUrl($order)}/review", ['rating' => 5, 'review_text' => 'Mantap'])
            ->assertOk();

        $page->assertSee('reviewPopup', false)
            ->assertSee($this->jsQuote().'open'.$this->jsQuote().':true', false)
            ->assertSee('5 / 5 Bintang');
    }

    public function test_the_popup_stays_closed_on_a_normal_visit(): void
    {
        $order = $this->orderWith(['Plain White Bread', 'Vanilla']);

        $this->get($this->statusUrl($order))
            ->assertOk()
            ->assertSee('reviewPopup', false)
            ->assertSee('reviewForm', false)
            ->assertSee($this->jsQuote().'open'.$this->jsQuote().':false', false);
    }
}
