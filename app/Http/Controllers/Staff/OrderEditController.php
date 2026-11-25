<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UpdateOrderItemsRequest;
use App\Models\Order;
use App\Services\OrderEditor;
use App\Support\OrderChangeSummary;
use Illuminate\Http\JsonResponse;

/**
 * Staff edits of an order that is already in (flavor out of stock, toppings swapped over WhatsApp). The page asks for a
 * preview first so the admin sees the before and after, the new total and any stock problem, then saves once.
 */
class OrderEditController extends Controller
{
    public function __construct(private readonly OrderEditor $editor) {}

    public function preview(UpdateOrderItemsRequest $request, Order $order): JsonResponse
    {
        $plan = $this->editor->plan($order, $request->validated('items'));

        return response()->json([
            'changes' => $plan['changes'],
            'before' => array_map(fn (array $c) => OrderChangeSummary::describe($c), $plan['before']),
            'after' => array_map(fn (array $c) => OrderChangeSummary::describe($c), $plan['after']),
            'total_before' => $plan['total_before'],
            'total_after' => $plan['total_after'],
            'difference' => $plan['difference'],
            'needs_payment_adjustment' => $order->hasBeenPaid() && abs($plan['difference']) >= 0.005,
            'stock' => collect($plan['deltas'])->map(fn (int $delta, int $id) => [
                'name' => $plan['components']->get($id)?->name ?? '#'.$id,
                'delta' => $delta,
                'left' => $plan['components']->get($id)?->stock_quantity,
            ])->values()->all(),
        ]);
    }

    public function update(UpdateOrderItemsRequest $request, Order $order): JsonResponse
    {
        $revision = $this->editor->apply(
            $order,
            $request->validated('items'),
            $request->validated('version'),
            trim($request->validated('reason')),
            $request->user()?->id,
        );

        $order->refresh();

        return response()->json([
            'message' => "Pesanan #{$order->order_number} diperbarui. Halaman tracking pembeli ikut berubah.",
            'order' => $this->editor->editorPayload($order),
            'whatsapp_url' => $this->editor->whatsappUrl($order, $revision),
            'total' => (float) $order->total_price,
        ]);
    }
}
