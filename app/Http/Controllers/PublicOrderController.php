<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PublicOrderController extends Controller
{
    /** Side-view cutouts used by the animated sandwich on the status page. */
    private const BREAD_ART = ['Whole Wheat Bread' => 'bread-wheat'];

    private const ICE_ART = ['Strawberry' => 'ice-strawberry', 'Chocolate' => 'ice-chocolate'];

    private const TOPPING_ART = ['Chocolate Sauce' => 'top-sauce', 'Rainbow Sprinkles' => 'top-sprinkles'];

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
                    // One line per ingredient with its own price, for the printed receipt.
                    'lines' => $items->map(fn (OrderItem $item) => [
                        'label' => match ($item->component?->category) {
                            'bread' => 'Roti',
                            'ice_cream' => 'Es Krim',
                            default => 'Topping',
                        },
                        'name' => $item->component?->name ?? '-',
                        'price' => (float) $item->unit_price,
                    ])->values(),
                    // Artwork for the animated stack; ingredients without art borrow a generic slab.
                    'art' => [
                        'bread' => self::BREAD_ART[$bread?->name] ?? 'bread-white',
                        'ice' => self::ICE_ART[$flavor?->name] ?? 'ice-vanilla',
                        'toppings' => $toppings
                            ->map(fn ($topping) => self::TOPPING_ART[$topping->name] ?? null)
                            ->filter()
                            ->values()
                            ->all(),
                    ],
                ];
            })
            ->values();

        return view('pages.order-status', [
            'order' => $order,
            'creations' => $creations,
        ]);
    }

    public function submitReview(Request $request, string $token): RedirectResponse|JsonResponse
    {
        $order = Order::where('confirmation_token', $token)->firstOrFail();

        if ($order->isCancelled()) {
            $message = 'Pesanan yang dibatalkan tidak dapat diulas.';

            return $request->wantsJson()
                ? response()->json(['message' => $message], 403)
                : back()->with('error', $message);
        }

        // One review per order: a leaked status link must not let someone replace it.
        if ($order->hasReview()) {
            $message = 'Pesanan ini sudah diulas. Terima kasih!';

            return $request->wantsJson()
                ? response()->json(['message' => $message], 409)
                : back()->with('error', $message);
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

        $thanks = 'Terima kasih atas ulasan manis Anda! Masukan Anda sangat berharga bagi kami.';

        // The status page sends the review in the background so it can play the thank-you popup.
        if ($request->wantsJson()) {
            return response()->json([
                'message' => $thanks,
                'rating' => $order->rating,
                'review_text' => $order->review_text,
            ]);
        }

        return back()->with('success', $thanks);
    }
}

