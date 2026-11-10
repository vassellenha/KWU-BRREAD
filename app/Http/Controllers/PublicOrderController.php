<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PublicOrderController extends Controller
{
    public function show(string $token): View
    {
        $order = Order::with(['orderItems.component'])
            ->where('confirmation_token', $token)
            ->firstOrFail();

        // Group items into sandwiches/creations by creation_index
        $creations = $order->orderItems
            ->groupBy('creation_index')
            ->map(function (Collection $items) {
                $bread = $items->first(fn (OrderItem $item) => $item->component?->category === 'bread')?->component;
                $flavor = $items->first(fn (OrderItem $item) => $item->component?->category === 'ice_cream')?->component;
                $toppings = $items->filter(fn (OrderItem $item) => $item->component?->category === 'topping')
                    ->map(fn (OrderItem $item) => $item->component)
                    ->values();

                $quantity = $items->first()?->quantity ?? 1;
                $unitPrice = (float) $items->sum(fn (OrderItem $item) => (float) $item->unit_price);
                $subtotal = $unitPrice * $quantity;

                return [
                    'bread' => $bread,
                    'flavor' => $flavor,
                    'toppings' => $toppings,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
            })
            ->values();

        return view('pages.order-status', [
            'order' => $order,
            'creations' => $creations,
        ]);
    }

    public function submitReview(Request $request, string $token): RedirectResponse
    {
        $order = Order::where('confirmation_token', $token)->firstOrFail();

        if ($order->isCancelled()) {
            return back()->with('error', 'Pesanan yang dibatalkan tidak dapat diulas.');
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'review_text' => ['nullable', 'string', 'max:1000'],
        ], [
            'rating.required' => 'Silakan pilih rating bintang 1 hingga 5.',
            'rating.between' => 'Rating bintang harus antara 1 sampai 5.',
            'review_text.max' => 'Ulasan maksimal 1.000 karakter.',
        ]);

        $order->update([
            'rating' => $validated['rating'],
            'review_text' => ! empty($validated['review_text']) ? trim($validated['review_text']) : null,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Terima kasih atas ulasan manis Anda! Masukan Anda sangat berharga bagi kami.');
    }
}

