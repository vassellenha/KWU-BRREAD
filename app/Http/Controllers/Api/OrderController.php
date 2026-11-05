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
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'component_ids' => ['required', 'array', 'min:1'],
            'component_ids.*' => ['integer', 'exists:components,id'],
        ]);

        $components = Component::whereIn('id', $validated['component_ids'])
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $categories = $components->pluck('category')->unique();

        if (! $categories->contains('bread')) {
            throw ValidationException::withMessages([
                'component_ids' => 'Please select at least one bread or wafer base.',
            ]);
        }

        if (! $categories->contains('ice_cream')) {
            throw ValidationException::withMessages([
                'component_ids' => 'Please select at least one ice cream flavor.',
            ]);
        }

        $totalPrice = 0;
        foreach ($validated['component_ids'] as $id) {
            $totalPrice += $components[$id]->price;
        }

        $order = DB::transaction(function () use ($validated, $totalPrice, $components) {
            $order = Order::create([
                'order_number' => 'BRREAD-'.strtoupper(Str::random(8)),
                'total_price' => $totalPrice,
                'status' => 'pending',
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
            ]);

            foreach ($validated['component_ids'] as $index => $componentId) {
                $order->orderItems()->create([
                    'component_id' => $componentId,
                    'layer_order' => $index,
                ]);

                $components[$componentId]->recordStockMovement(
                    type: StockMovement::TYPE_CONSUMPTION,
                    quantityChange: -1,
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
