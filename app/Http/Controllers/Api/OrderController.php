<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\Order;
use App\Models\StockMovement;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
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
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255', new PersonName],
            'customer_phone' => ['required', 'string', 'max:30', new PhoneNumber],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.component_ids' => ['required', 'array', 'min:2', 'max:10'],
            'items.*.component_ids.*' => ['integer', 'exists:components,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ], [
            'items.max' => 'A cart can hold up to 20 different creations.',
            'items.*.quantity.max' => 'Each creation can be ordered up to 20 at a time.',
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
                $this->ensureCreationIsComplete($item['component_ids'], $components, $index + 1);
            }

            foreach ($quantityNeeded as $id => $needed) {
                $this->ensureStockCovers($components->get($id), $needed);
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

    /**
     * A sandwich is exactly one bread and one ice cream slab, each topping at most once.
     *
     * @param  list<int>  $componentIds
     * @param  Collection<int, Component>  $components
     */
    private function ensureCreationIsComplete(array $componentIds, Collection $components, int $creationNumber): void
    {
        $categories = collect($componentIds)->map(fn (int $id) => $components->get($id)?->category);

        $message = match (true) {
            $categories->contains(null) => "Creation #{$creationNumber} uses an ingredient that is no longer available.",
            count(array_unique($componentIds)) !== count($componentIds) => "Creation #{$creationNumber} lists the same ingredient twice.",
            $categories->filter(fn (string $category) => $category === 'bread')->count() !== 1 => "Creation #{$creationNumber} needs exactly one bread base.",
            $categories->filter(fn (string $category) => $category === 'ice_cream')->count() !== 1 => "Creation #{$creationNumber} needs exactly one ice cream flavor.",
            default => null,
        };

        if ($message !== null) {
            throw ValidationException::withMessages(['items' => $message]);
        }
    }

    private function ensureStockCovers(?Component $component, int $needed): void
    {
        if ($component === null || $component->stock_quantity >= $needed) {
            return;
        }

        throw ValidationException::withMessages([
            'items' => $component->stock_quantity > 0
                ? "Only {$component->stock_quantity} {$component->name} left, but this order needs {$needed}. Lower the quantity and try again."
                : "{$component->name} just sold out. Remove it from your cart and try again.",
        ]);
    }
}
