<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\Order;
use App\Models\StockMovement;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use App\Services\OrderCreationRules;
use App\Support\ContactInfo;
use App\Support\ShopSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // The superadmin can pause checkout under Website in the panel; the cart shows the same message.
        $shop = ShopSettings::get();
        if (! $shop['orders_open']) {
            return response()->json(['message' => $shop['orders_paused_message']], 503);
        }

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255', new PersonName],
            'customer_phone' => ['required', 'string', 'max:30', new PhoneNumber],
            'admin_name' => ['nullable', 'string', 'max:80'],
            'items' => ['required', 'list', 'min:1', 'max:20'],
            'items.*' => ['required', 'array'],
            'items.*.component_ids' => ['required', 'list', 'min:2', 'max:10'],
            'items.*.component_ids.*' => ['integer', 'exists:components,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ], [
            'items.max' => 'A cart can hold up to 20 different creations.',
            'items.*.quantity.max' => 'Each creation can be ordered up to 20 at a time.',
        ]);

        $items = array_values($validated['items']);

        // The atmint the customer is about to chat with. Only a name that is really on the list is kept.
        $chatAdmin = collect(ContactInfo::get()['whatsapp_links'])->pluck('name')->first(fn (string $name) => $name === ($validated['admin_name'] ?? null));

        // Tally total units needed per component across every creation, multiplied by its cart quantity.
        $quantityNeeded = [];
        foreach ($items as $item) {
            foreach ($item['component_ids'] as $id) {
                $quantityNeeded[$id] = ($quantityNeeded[$id] ?? 0) + $item['quantity'];
            }
        }

        $order = DB::transaction(function () use ($validated, $items, $quantityNeeded, $chatAdmin) {
            // Lock the rows for the duration of the transaction so two customers racing
            // for the last unit of something can never both succeed.
            $components = Component::whereIn('id', array_keys($quantityNeeded))
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $index => $item) {
                OrderCreationRules::ensureComplete($item['component_ids'], $components, $index + 1);
            }

            foreach ($quantityNeeded as $id => $needed) {
                OrderCreationRules::ensureStockCovers($components->get($id), $needed);
            }

            $totalPrice = 0;
            foreach ($items as $item) {
                foreach ($item['component_ids'] as $id) {
                    $totalPrice += $components[$id]->price * $item['quantity'];
                }
            }

            $order = Order::create([
                'order_number' => 'BRREAD-'.strtoupper(Str::random(8)),
                'total_price' => $totalPrice,
                'status' => 'pending',
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'chat_admin' => $chatAdmin,
            ]);

            foreach ($items as $creationIndex => $item) {
                foreach ($item['component_ids'] as $layerOrder => $componentId) {
                    $order->orderItems()->create([
                        'component_id' => $componentId,
                        'creation_index' => $creationIndex,
                        'layer_order' => $layerOrder,
                        'quantity' => $item['quantity'],
                        'unit_price' => $components[$componentId]->price,
                    ]);
                }
            }

            foreach ($quantityNeeded as $id => $needed) {
                $components[$id]->recordStockMovement(
                    type: StockMovement::TYPE_CONSUMPTION,
                    quantityChange: -$needed,
                    orderId: $order->id,
                );
            }

            return $order;
        });

        return response()->json([
            'success' => true,
            'order_number' => $order->order_number,
            'total_price' => $order->total_price,
        ], 201);
    }
}
