<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255', 'regex:/^[\p{L}][\p{L}\s.\'-]*$/u'],
            'customer_phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s-]*$/'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.component_ids' => ['required', 'array', 'min:1'],
            'items.*.component_ids.*' => ['integer', 'exists:components,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $items = $validated['items'];

        // Tally total units needed per component across every creation, multiplied by its cart quantity.
        $quantityNeeded = [];
        foreach ($items as $item) {
            foreach ($item['component_ids'] as $id) {
                $quantityNeeded[$id] = ($quantityNeeded[$id] ?? 0) + $item['quantity'];
            }
        }

        $order = DB::transaction(function () use ($validated, $items, $quantityNeeded) {
            // Lock the rows for the duration of the transaction so two customers racing
            // for the last unit of something can never both succeed.
            $components = Component::whereIn('id', array_keys($quantityNeeded))
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $index => $item) {
                $categories = collect($item['component_ids'])
                    ->map(fn ($id) => $components->get($id)?->category)
                    ->filter();

                if (! $categories->contains('bread')) {
                    throw ValidationException::withMessages([
                        'items' => 'Creation #'.($index + 1).' needs a bread or wafer base.',
                    ]);
                }

                if (! $categories->contains('ice_cream')) {
                    throw ValidationException::withMessages([
                        'items' => 'Creation #'.($index + 1).' needs an ice cream flavor.',
                    ]);
                }
            }

            foreach ($quantityNeeded as $id => $needed) {
                $component = $components->get($id);

                if (! $component || $component->stock_quantity < $needed) {
                    throw ValidationException::withMessages([
                        'items' => ($component->name ?? 'One of your ingredients').' just sold out. Please pick something else.',
                    ]);
                }
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
            ]);

            foreach ($items as $creationIndex => $item) {
                foreach ($item['component_ids'] as $layerOrder => $componentId) {
                    $order->orderItems()->create([
                        'component_id' => $componentId,
                        'creation_index' => $creationIndex,
                        'layer_order' => $layerOrder,
                        'quantity' => $item['quantity'],
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
