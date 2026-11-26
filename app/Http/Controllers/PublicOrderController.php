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
use Illuminate\Support\Arr;
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

        // The latest staff edit, for the "Pesanan diubah" note. The admin's name and the full history stay internal.
        $revision = $order->revisions()->latest('id')->first();

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
            'order' => $order->loadMissing('reviewPhotos:id,order_id,width,height,updated_at,fit,focus_x,focus_y,zoom'),
            'revision' => $revision,
            'version' => $this->versionOf($order),
            'creations' => $creations,
            // Staff and the superadmin can open a customer's status page, but only to look: a review must come from the customer.
            'viewOnly' => auth()->check(),
        ]);
    }

    /** A cheap fingerprint the status page polls: it changes when the status or the contents of the order change. */
    public function version(Request $request, string $token): JsonResponse|Response
    {
        $order = Order::where('confirmation_token', $token)->firstOrFail();
        $version = $this->versionOf($order);

        $response = response()->json(['version' => $version, 'status' => $order->status])
            ->setEtag($version)
            ->header('Cache-Control', 'no-cache, private');

        // A matching If-None-Match turns this into an empty 304.
        $response->isNotModified($request);

        return $response;
    }

    private function versionOf(Order $order): string
    {
        return substr(sha1($order->id.'|'.$order->status.'|'.$order->total_price.'|'.$order->updated_at?->format('Y-m-d H:i:s.u').'|'.$order->revisions()->count()), 0, 16);
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

        // "Kirim Ulasan & Selesaikan": the review that also closes the order. It needs a comment, and the sandwich ready.
        $finish = $request->boolean('finish');

        if ($finish && ! $order->isReady()) {
            $message = 'Pesanan baru bisa diselesaikan setelah siap diambil.';

            return $request->wantsJson()
                ? response()->json(['message' => $message], 409)
                : back()->with('error', $message);
        }

        // Up to four photos (`photos[]`; the old single `photo` still works). They always need a few words with them.
        $files = array_values(array_filter(array_merge(
            Arr::wrap($request->file('photos')),
            Arr::wrap($request->file('photo')),
        )));
        $hasPhotos = $files !== [];

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'review_text' => [$finish ? 'required' : 'nullable', 'string', 'max:1000', 'required_with:photo,photos', 'min:'.($hasPhotos ? 10 : ($finish ? 3 : 0))],
            'photos' => ['nullable', 'array', 'max:'.ReviewPhoto::MAX_PER_REVIEW],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144', 'dimensions:min_width=200,min_height=200,max_width=8000,max_height=8000'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144', 'dimensions:min_width=200,min_height=200,max_width=8000,max_height=8000'],
        ], [
            'rating.required' => 'Silakan pilih rating bintang 1 hingga 5.',
            'rating.between' => 'Rating bintang harus antara 1 sampai 5.',
            'review_text.max' => 'Ulasan maksimal 1.000 karakter.',
            'review_text.required_with' => 'Foto harus disertai ulasan. Tulis sedikit tentang sandwich-nya ya.',
            'review_text.required' => 'Tulis komentar dulu untuk menyelesaikan pesanan.',
            'review_text.min' => $hasPhotos ? 'Ulasan untuk foto minimal 10 karakter.' : 'Komentar minimal 3 karakter.',
            'photos.max' => 'Foto maksimal '.ReviewPhoto::MAX_PER_REVIEW.' per ulasan.',
            'photos.*.image' => 'File harus berupa foto (JPG, PNG atau WebP).',
            'photos.*.mimes' => 'File harus berupa foto (JPG, PNG atau WebP).',
            'photos.*.max' => 'Setiap foto maksimal 6 MB.',
            'photos.*.dimensions' => 'Foto terlalu kecil atau terlalu besar (minimal 200 px per sisi).',
            'photos.*.uploaded' => 'Foto gagal diunggah. Coba foto yang lebih kecil.',
            'photo.image' => 'File harus berupa foto (JPG, PNG atau WebP).',
            'photo.mimes' => 'File harus berupa foto (JPG, PNG atau WebP).',
            'photo.max' => 'Foto maksimal 6 MB.',
            'photo.dimensions' => 'Foto terlalu kecil atau terlalu besar (minimal 200 px per sisi).',
            'photo.uploaded' => 'Foto gagal diunggah. Coba foto yang lebih kecil.',
        ]);

        if (count($files) > ReviewPhoto::MAX_PER_REVIEW) {
            $message = 'Foto maksimal '.ReviewPhoto::MAX_PER_REVIEW.' per ulasan.';

            return $request->wantsJson()
                ? response()->json(['message' => $message, 'errors' => ['photos' => [$message]]], 422)
                : back()->withErrors(['photos' => $message])->withInput();
        }

        $pictures = [];
        foreach ($files as $file) {
            try {
                $pictures[] = ReviewImage::fromUpload($file);
            } catch (Throwable) {
                $message = 'Foto tidak bisa dibaca. Coba foto lain ya.';

                return $request->wantsJson()
                    ? response()->json(['message' => $message, 'errors' => ['photos' => [$message]]], 422)
                    : back()->withErrors(['photos' => $message])->withInput();
            }
        }

        DB::transaction(function () use ($order, $validated, $pictures, $finish) {
            $order->update([
                'rating' => $validated['rating'],
                'review_text' => ! empty($validated['review_text']) ? trim($validated['review_text']) : null,
                'reviewed_at' => now(),
            ] + ($finish ? ['status' => Order::STATUS_COMPLETED, 'completed_at' => now()] : []));
            foreach ($pictures as $picture) {
                $order->reviewPhotos()->create($picture);
            }
        });

        $thanks = 'Terima kasih atas ulasan manis Anda! Masukan Anda sangat berharga bagi kami.';

        // The status page sends the review in the background so it can play the thank-you popup.
        if ($request->wantsJson()) {
            return response()->json([
                'message' => $thanks,
                'rating' => $order->rating,
                'review_text' => $order->review_text,
                'photo' => $order->reviewPhotos()->first()?->url(),
                'photos' => $order->reviewPhotos()->get()->map->url()->all(),
                'completed' => $order->isCompleted(),
            ]);
        }

        return back()->with('success', $thanks);
    }

    /**
     * The customer closes their own order from the tracking link. It has to be ready, and a rating and a comment have to
     * be in first (the photo is optional). A review that has stars but no words gets its comment here.
     */
    public function complete(Request $request, string $token): RedirectResponse
    {
        $order = Order::where('confirmation_token', $token)->firstOrFail();

        if ($request->user()) {
            return back()->with('error', 'Akun staf hanya bisa melihat. Pesanan diselesaikan oleh pelanggan atau lewat tombol Selesai di daftar pesanan.');
        }

        if ($order->isCancelled()) {
            return back()->with('error', 'Pesanan yang dibatalkan tidak dapat diselesaikan.');
        }

        if ($order->isCompleted()) {
            return back()->with('info', 'Pesanan ini sudah selesai. Terima kasih!');
        }

        if (! $order->isReady()) {
            return back()->with('error', 'Pesanan baru bisa diselesaikan setelah siap diambil.');
        }

        if (! $order->hasReview()) {
            return back()->with('error', 'Beri bintang dan tulis ulasan dulu sebelum menyelesaikan pesanan.');
        }

        $comment = null;
        if (trim((string) $order->review_text) === '') {
            $comment = $request->validate([
                'review_text' => ['required', 'string', 'min:3', 'max:1000'],
            ], [
                'review_text.required' => 'Tulis komentar dulu untuk menyelesaikan pesanan.',
                'review_text.min' => 'Komentar minimal 3 karakter.',
                'review_text.max' => 'Ulasan maksimal 1.000 karakter.',
            ])['review_text'];
        }

        $order->update(array_filter([
            'review_text' => $comment !== null ? trim($comment) : null,
            'status' => Order::STATUS_COMPLETED,
            'completed_at' => now(),
        ], fn ($value) => $value !== null));

        return back()->with('success', 'Pesanan selesai. Terima kasih sudah jajan di Brread!');
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
