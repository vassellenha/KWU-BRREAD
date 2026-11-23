<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReviewPhoto;
use App\Support\ReviewFeed;
use App\Support\ReviewImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class PublicOrderController extends Controller
{
    /** Side-view cutouts used by the animated sandwich on the status page. */
    private const BREAD_ART = ['Whole Wheat Bread' => 'bread-wheat'];

    private const ICE_ART = [
        'Strawberry' => 'ice-strawberry',
        'Chocolate' => 'ice-chocolate',
        'Neapolitan' => 'ice-neapolitan',
        'Durian Musang King' => 'ice-durian-musang-king',
        'Es Teler' => 'ice-es-teler',
        'Matcha' => 'ice-matcha',
    ];

    private const TOPPING_ART = [
        'Chocolate Sauce' => 'top-sauce',
        'Rainbow Sprinkles' => 'top-sprinkles',
        'Marshmallow Mini' => 'top-marshmallow',
        'Yupi Gummy Bear Mini' => 'top-gummy-bears',
    ];

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
            'order' => $order->loadMissing('reviewPhoto:id,order_id,width,height,updated_at'),
            'creations' => $creations,
            // Staff and the superadmin can open a customer's status page, but only to look: a review must come from the customer.
            'viewOnly' => auth()->check(),
        ]);
    }

    /** The customer confirms the paid order reached them. Only a paid order can be received, and only once. */
    public function receive(Request $request, string $token): RedirectResponse
    {
        // A signed-in account is staff or the superadmin: they may look at the page, never receive the order for the customer.
        if ($request->user()) {
            return back()->with('error', 'Akun staf hanya bisa melihat. Pesanan hanya bisa diterima oleh pelanggan.');
        }

        $outcome = DB::transaction(function () use ($token) {
            $order = Order::where('confirmation_token', $token)->lockForUpdate()->firstOrFail();

            if ($order->isCancelled()) {
                return ['error', 'Pesanan yang dibatalkan tidak bisa diterima.'];
            }

            if ($order->isCompleted()) {
                return ['info', 'Pesanan ini sudah kamu tandai diterima. Makasih!'];
            }

            if (! $order->isPaid()) {
                return ['error', 'Pesanan baru bisa diterima setelah pembayarannya dicatat admin.'];
            }

            $order->update(['status' => Order::STATUS_COMPLETED, 'received_at' => now()]);

            return ['success', 'Makasih! Pesananmu sudah ditandai diterima. Selamat menikmati.'];
        });

        // The thank-you popup of the review form also listens to "success"; this flash only needs the banner.
        return back()->with(...$outcome)->with('received', $outcome[0] === 'success');
    }

    public function submitReview(Request $request, string $token): RedirectResponse|JsonResponse
    {
        $order = Order::where('confirmation_token', $token)->firstOrFail();

        // A signed-in account is staff or the superadmin: they may look at the page, never rate the order.
        if ($request->user()) {
            $message = 'Akun staf hanya bisa melihat. Ulasan hanya bisa dikirim oleh pelanggan.';

            return $request->wantsJson()
                ? response()->json(['message' => $message], 403)
                : back()->with('error', $message);
        }

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

        // A photo always needs a few words with it: a picture alone says nothing about the sandwich.
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'review_text' => ['nullable', 'string', 'max:1000', 'required_with:photo', 'min:'.($request->hasFile('photo') ? 10 : 0)],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144', 'dimensions:min_width=200,min_height=200,max_width=8000,max_height=8000'],
        ], [
            'rating.required' => 'Silakan pilih rating bintang 1 hingga 5.',
            'rating.between' => 'Rating bintang harus antara 1 sampai 5.',
            'review_text.max' => 'Ulasan maksimal 1.000 karakter.',
            'review_text.required_with' => 'Foto harus disertai ulasan. Tulis sedikit tentang sandwich-nya ya.',
            'review_text.min' => 'Ulasan untuk foto minimal 10 karakter.',
            'photo.image' => 'File harus berupa foto (JPG, PNG atau WebP).',
            'photo.mimes' => 'File harus berupa foto (JPG, PNG atau WebP).',
            'photo.max' => 'Foto maksimal 6 MB.',
            'photo.dimensions' => 'Foto terlalu kecil atau terlalu besar (minimal 200 px per sisi).',
            'photo.uploaded' => 'Foto gagal diunggah. Coba foto yang lebih kecil.',
        ]);

        $picture = null;
        if ($request->hasFile('photo')) {
            try {
                $picture = ReviewImage::fromUpload($request->file('photo'));
            } catch (Throwable) {
                $message = 'Foto tidak bisa dibaca. Coba foto lain ya.';

                return $request->wantsJson()
                    ? response()->json(['message' => $message, 'errors' => ['photo' => [$message]]], 422)
                    : back()->withErrors(['photo' => $message])->withInput();
            }
        }

        DB::transaction(function () use ($order, $validated, $picture) {
            $order->update([
                'rating' => $validated['rating'],
                'review_text' => ! empty($validated['review_text']) ? trim($validated['review_text']) : null,
                'reviewed_at' => now(),
            ]);
            if ($picture) {
                $order->reviewPhoto()->create($picture);
            }
        });

        $thanks = 'Terima kasih atas ulasan manis Anda! Masukan Anda sangat berharga bagi kami.';

        // The status page sends the review in the background so it can play the thank-you popup.
        if ($request->wantsJson()) {
            return response()->json([
                'message' => $thanks,
                'rating' => $order->rating,
                'review_text' => $order->review_text,
                'photo' => $order->reviewPhoto?->url(),
            ]);
        }

        return back()->with('success', $thanks);
    }

    /** A review photo, straight from the database. Its address carries a version, so it is cached for a year. */
    public function photo(ReviewPhoto $photo): Response
    {
        return response(base64_decode($photo->data), 200, [
            'Content-Type' => $photo->mime,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }

    /** Every customer photo (the thirty newest) and the newest words-only reviews. */
    public function reviews(): View
    {
        return view('pages.reviews', [
            'photos' => ReviewFeed::photos(ReviewFeed::WALL_MAX),
            'words' => ReviewFeed::words(ReviewFeed::WALL_MAX),
        ]);
    }
}
