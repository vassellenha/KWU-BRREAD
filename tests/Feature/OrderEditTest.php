<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Order;
use App\Models\OrderRevision;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\ComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderEditTest extends TestCase
{
    use RefreshDatabase;

    private Component $bread;

    private Component $flavor;

    private Component $otherFlavor;

    private Component $topping;

    private Component $otherTopping;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);

        $this->bread = Component::where('category', 'bread')->firstOrFail();
        $flavors = Component::where('category', 'ice_cream')->take(2)->get();
        $toppings = Component::where('category', 'topping')->take(2)->get();
        $this->flavor = $flavors[0];
        $this->otherFlavor = $flavors[1];
        $this->topping = $toppings[0];
        $this->otherTopping = $toppings[1];

        foreach ([$this->bread, $this->flavor, $this->otherFlavor, $this->topping, $this->otherTopping] as $component) {
            $component->update(['stock_quantity' => 50, 'is_active' => true]);
        }
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function order(string $status = Order::STATUS_CONFIRMED, int $quantity = 1): Order
    {
        $number = $this->postJson('/api/orders', [
            'customer_name' => 'Pembeli Edit',
            'customer_phone' => '081200000009',
            'items' => [['component_ids' => [$this->bread->id, $this->flavor->id, $this->topping->id], 'quantity' => $quantity]],
        ])->assertCreated()->json('order_number');

        $order = Order::where('order_number', $number)->firstOrFail();
        $order->forceFill(['status' => $status, 'confirmation_token' => 'tok-'.$order->id])->save();

        return $order->fresh();
    }

    private function items(array $ids, int $quantity = 1): array
    {
        return [['index' => $this->firstIndex($this->currentOrder), 'component_ids' => $ids, 'quantity' => $quantity]];
    }

    private ?Order $currentOrder = null;

    private function firstIndex(Order $order): int
    {
        return (int) $order->orderItems()->min('creation_index');
    }

    private function save(Order $order, array $ids, int $quantity = 1, string $reason = 'Rasa kosong, diganti lewat WA', ?string $version = null)
    {
        $this->currentOrder = $order;
        $version ??= app(\App\Services\OrderEditor::class)->version($order->fresh());

        return $this->actingAs($this->staff())->putJson(route('staff.orders.edit.save', $order), [
            'items' => $this->items($ids, $quantity),
            'version' => $version,
            'reason' => $reason,
        ]);
    }

    public function test_swapping_flavor_and_topping_recalculates_total_and_keeps_old_prices(): void
    {
        $order = $this->order();
        $oldLine = $order->orderItems()->where('component_id', $this->bread->id)->first();
        $expected = (float) $this->bread->price + (float) $this->otherFlavor->price + (float) $this->otherTopping->price;

        $this->save($order, [$this->bread->id, $this->otherFlavor->id, $this->otherTopping->id])->assertOk();

        $order->refresh();
        $this->assertEqualsWithDelta($expected, (float) $order->total_price, 0.01);
        $this->assertEqualsWithDelta((float) $oldLine->unit_price, (float) $order->orderItems()->where('component_id', $this->bread->id)->value('unit_price'), 0.001);
        $this->assertTrue($order->orderItems()->where('component_id', $this->otherFlavor->id)->exists());
        $this->assertFalse($order->orderItems()->where('component_id', $this->flavor->id)->exists());
    }

    public function test_stock_is_restored_and_deducted_with_one_movement_per_changed_component(): void
    {
        $order = $this->order();
        $flavorBefore = $this->flavor->fresh()->stock_quantity;
        $otherBefore = $this->otherFlavor->fresh()->stock_quantity;
        $breadBefore = $this->bread->fresh()->stock_quantity;
        $movements = StockMovement::count();

        $this->save($order, [$this->bread->id, $this->otherFlavor->id, $this->topping->id])->assertOk();

        $this->assertSame($flavorBefore + 1, $this->flavor->fresh()->stock_quantity);
        $this->assertSame($otherBefore - 1, $this->otherFlavor->fresh()->stock_quantity);
        $this->assertSame($breadBefore, $this->bread->fresh()->stock_quantity);
        $this->assertSame($movements + 2, StockMovement::count());
    }

    public function test_quantity_change_moves_only_the_difference(): void
    {
        $order = $this->order(quantity: 1);
        $breadBefore = $this->bread->fresh()->stock_quantity;

        $this->save($order, [$this->bread->id, $this->flavor->id, $this->topping->id], 3)->assertOk();

        $this->assertSame($breadBefore - 2, $this->bread->fresh()->stock_quantity);
    }

    public function test_it_rejects_a_flavor_that_is_out_of_stock_or_inactive(): void
    {
        $order = $this->order();
        $this->otherFlavor->update(['stock_quantity' => 0]);
        $this->save($order, [$this->bread->id, $this->otherFlavor->id, $this->topping->id])->assertStatus(422);
        $this->assertTrue($order->orderItems()->where('component_id', $this->flavor->id)->exists());

        $this->otherFlavor->update(['stock_quantity' => 50, 'is_active' => false]);
        $this->save($order, [$this->bread->id, $this->otherFlavor->id, $this->topping->id])->assertStatus(422);
        $this->assertSame(0, OrderRevision::count());
    }

    public function test_only_unfinished_orders_can_be_edited(): void
    {
        foreach ([Order::STATUS_PROCESSING, Order::STATUS_READY, Order::STATUS_CANCELLED] as $status) {
            $order = $this->order($status);
            $this->save($order, [$this->bread->id, $this->otherFlavor->id, $this->topping->id])->assertStatus(422);
        }

        foreach ([Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_PAID] as $status) {
            $order = $this->order($status);
            $this->save($order, [$this->bread->id, $this->otherFlavor->id, $this->topping->id])->assertOk();
        }
    }

    public function test_guests_and_roles_without_the_orders_module_are_kept_out(): void
    {
        $order = $this->order();
        $payload = ['items' => [], 'version' => 'x', 'reason' => 'abc'];

        $this->putJson(route('staff.orders.edit.save', $order), $payload)->assertUnauthorized();

        $hr = User::factory()->create(['role' => User::ROLE_HR]);
        $this->actingAs($hr)->putJson(route('staff.orders.edit.save', $order), $payload)->assertForbidden();
    }

    public function test_a_reason_is_mandatory(): void
    {
        $order = $this->order();
        $this->save($order, [$this->bread->id, $this->otherFlavor->id, $this->topping->id], reason: '')->assertStatus(422)->assertJsonValidationErrors('reason');
    }

    public function test_a_stale_version_is_rejected_as_a_conflict(): void
    {
        $order = $this->order();

        $this->save($order, [$this->bread->id, $this->otherFlavor->id, $this->topping->id], version: 'stale')->assertStatus(409);
        $this->assertSame(0, OrderRevision::count());
    }

    public function test_a_save_records_one_revision_with_before_and_after(): void
    {
        $order = $this->order();
        $totalBefore = (float) $order->total_price;

        $this->save($order, [$this->bread->id, $this->otherFlavor->id, $this->topping->id], reason: 'Neapolitan habis')->assertOk()->assertJsonStructure(['message', 'order', 'whatsapp_url', 'total']);

        $this->assertSame(1, OrderRevision::count());
        $revision = OrderRevision::first();
        $this->assertSame('Neapolitan habis', $revision->reason);
        $this->assertEqualsWithDelta($totalBefore, (float) $revision->total_before, 0.01);
        $this->assertNotEmpty($revision->before);
        $this->assertNotEmpty($revision->after);
    }

    public function test_a_change_without_differences_is_refused(): void
    {
        $order = $this->order();
        $this->save($order, [$this->bread->id, $this->flavor->id, $this->topping->id])->assertStatus(422);
    }

    public function test_the_whatsapp_link_has_the_changes_and_the_tracking_link(): void
    {
        $order = $this->order();

        $url = $this->save($order, [$this->bread->id, $this->otherFlavor->id, $this->topping->id])->assertOk()->json('whatsapp_url');

        $this->assertStringStartsWith('https://wa.me/', $url);
        $text = urldecode($url);
        $this->assertStringContainsString($this->otherFlavor->name, $text);
        $this->assertStringContainsString('/order/status/tok-'.$order->id, $text);
    }

    public function test_the_preview_changes_nothing(): void
    {
        $order = $this->order();
        $this->currentOrder = $order;

        $this->actingAs($this->staff())->postJson(route('staff.orders.edit.preview', $order), [
            'items' => $this->items([$this->bread->id, $this->otherFlavor->id, $this->topping->id]),
        ])->assertOk()->assertJsonStructure(['changes', 'before', 'after', 'total_before', 'total_after', 'difference', 'stock']);

        $this->assertSame(0, OrderRevision::count());
        $this->assertTrue($order->orderItems()->where('component_id', $this->flavor->id)->exists());
    }

    public function test_the_tracking_page_and_version_endpoint_follow_the_edit(): void
    {
        $order = $this->order();
        $before = $this->get(route('order.version', $order->confirmation_token))->assertOk()->json('version');

        $this->save($order, [$this->bread->id, $this->otherFlavor->id, $this->topping->id], reason: 'Rasa diganti')->assertOk();

        $after = $this->get(route('order.version', $order->confirmation_token))->assertOk()->json('version');
        $this->assertNotSame($before, $after);

        $this->get(route('order.status', $order->confirmation_token))
            ->assertOk()
            ->assertSee('Pesanan diubah oleh admin')
            ->assertSee('Rasa diganti')
            ->assertSee($this->otherFlavor->name);

        $etag = $this->get(route('order.version', $order->confirmation_token))->headers->get('ETag');
        $this->withHeaders(['If-None-Match' => $etag])->get(route('order.version', $order->confirmation_token))->assertStatus(304);
    }

    public function test_the_staff_list_offers_the_edit_button_only_for_editable_orders(): void
    {
        $open = $this->order(Order::STATUS_CONFIRMED);
        $this->order(Order::STATUS_READY);

        $this->actingAs($this->staff())->get(route('staff.orders.index'))
            ->assertOk()
            ->assertSee('Edit pesanan')
            ->assertSee($open->order_number);
    }
}
