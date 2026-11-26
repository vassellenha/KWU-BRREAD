<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\ReviewImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Review management (superadmin): every review customers left, with filters, so a joke or abusive one can be hidden
 * from the site, its photo taken down, or the whole review removed. Hide and photo take-down reuse the Website routes.
 */
class ReviewController extends Controller
{
    private const FILTERS = [
        'all' => 'Semua ulasan',
        'shown' => 'Tampil di situs',
        'hidden' => 'Disembunyikan',
        'photo' => 'Dengan foto',
        'words' => 'Teks saja',
        'low' => 'Bintang 1 sampai 2',
    ];

    public function index(Request $request): View
    {
        $filter = (string) $request->query('show', 'all');
        $filter = array_key_exists($filter, self::FILTERS) ? $filter : 'all';
        $search = trim((string) $request->query('q', ''));

        $query = Order::query()
            ->whereNotNull('rating')
            ->with('reviewPhoto:id,order_id,width,height,updated_at,fit,focus_x,focus_y,zoom')
            ->latest('reviewed_at');

        match ($filter) {
            'shown' => $query->whereNull('review_hidden_at'),
            'hidden' => $query->whereNotNull('review_hidden_at'),
            'photo' => $query->whereHas('reviewPhoto'),
            'words' => $query->whereDoesntHave('reviewPhoto'),
            'low' => $query->where('rating', '<=', 2),
            default => null,
        };

        if ($search !== '') {
            $needle = '%'.preg_replace('/[!%_]/', '!$0', Str::lower($search)).'%';
            $query->where(function ($q) use ($needle) {
                foreach (['order_number', 'customer_name', 'review_text'] as $column) {
                    $q->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$needle]);
                }
            });
        }

        $reviews = Order::query()->whereNotNull('rating');

        return view('staff.reviews.index', [
            'reviews' => $query->paginate(12)->withQueryString(),
            'filters' => self::FILTERS,
            'filter' => $filter,
            'search' => $search,
            // Finished (or ready) orders nobody has reviewed yet: the admin can add a review for them.
            'reviewable' => Order::query()
                ->whereIn('status', [Order::STATUS_READY, Order::STATUS_COMPLETED])
                ->whereNull('rating')
                ->latest()
                ->get(['id', 'order_number', 'customer_name', 'status']),
            'stats' => [
                'total' => (clone $reviews)->count(),
                'average' => round((float) (clone $reviews)->avg('rating'), 1),
                'hidden' => (clone $reviews)->whereNotNull('review_hidden_at')->count(),
                'photos' => (clone $reviews)->whereHas('reviewPhoto')->count(),
                'fresh' => (clone $reviews)->where('reviewed_at', '>=', now()->subDay())->count(),
            ],
        ]);
    }

    /** Adds a review for a finished order, on the customer's behalf (a customer who asked the shop to post it). */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', Rule::exists('orders', 'id')],
            'rating' => ['required', 'integer', 'between:1,5'],
            'review_text' => ['required', 'string', 'min:3', 'max:1000'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144', 'dimensions:min_width=200,min_height=200,max_width=8000,max_height=8000'],
        ], [
            'order_id.required' => 'Pilih pesanannya dulu.',
            'rating.required' => 'Pilih bintang 1 sampai 5.',
            'review_text.required' => 'Tulis isi ulasannya.',
            'review_text.min' => 'Ulasan minimal 3 karakter.',
            'photo.image' => 'File harus berupa foto (JPG, PNG atau WebP).',
            'photo.mimes' => 'File harus berupa foto (JPG, PNG atau WebP).',
            'photo.max' => 'Foto maksimal 6 MB.',
            'photo.dimensions' => 'Foto terlalu kecil atau terlalu besar (minimal 200 px per sisi).',
        ]);

        $order = Order::findOrFail($validated['order_id']);

        if (! $order->hasBeenReady()) {
            return back()->withErrors(['order_id' => 'Hanya pesanan yang sudah siap diambil atau selesai yang bisa diberi ulasan.'])->withInput();
        }

        if ($order->hasReview()) {
            return back()->withErrors(['order_id' => 'Pesanan ini sudah punya ulasan. Hapus dulu kalau mau diganti.'])->withInput();
        }

        $picture = null;
        if ($request->hasFile('photo')) {
            try {
                $picture = ReviewImage::fromUpload($request->file('photo'));
            } catch (Throwable) {
                return back()->withErrors(['photo' => 'Foto tidak bisa dibaca. Coba foto lain.'])->withInput();
            }
        }

        DB::transaction(function () use ($order, $validated, $picture) {
            $order->update([
                'rating' => $validated['rating'],
                'review_text' => trim($validated['review_text']),
                'reviewed_at' => now(),
                'review_hidden_at' => null,
            ]);
            if ($picture) {
                $order->reviewPhoto()->create($picture);
            }
        });

        return back()->with('status', 'Ulasan untuk pesanan #'.$order->order_number.' ditambahkan.');
    }

    /** Replaces the photo of a review (or adds one) and sets how it sits in the frame on the public pages. */
    public function updatePhoto(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->hasReview(), 404);

        $validated = $request->validate([
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144', 'dimensions:min_width=200,min_height=200,max_width=8000,max_height=8000'],
            'fit' => ['required', Rule::in(['auto', 'cover', 'contain'])],
            'zoom' => ['required', 'integer', 'between:100,300'],
            'focus_x' => ['required', 'integer', 'between:0,100'],
            'focus_y' => ['required', 'integer', 'between:0,100'],
        ], [
            'photo.image' => 'File harus berupa foto (JPG, PNG atau WebP).',
            'photo.mimes' => 'File harus berupa foto (JPG, PNG atau WebP).',
            'photo.max' => 'Foto maksimal 6 MB.',
            'photo.dimensions' => 'Foto terlalu kecil atau terlalu besar (minimal 200 px per sisi).',
        ]);

        $existing = $order->reviewPhoto;

        if (! $existing && ! $request->hasFile('photo')) {
            return back()->withErrors(['photo' => 'Pilih foto dulu.']);
        }

        $attributes = Arr::only($validated, ['fit', 'zoom', 'focus_x', 'focus_y']);

        if ($request->hasFile('photo')) {
            try {
                $attributes += ReviewImage::fromUpload($request->file('photo'));
            } catch (Throwable) {
                return back()->withErrors(['photo' => 'Foto tidak bisa dibaca. Coba foto lain.']);
            }
        }

        $existing
            ? $existing->update($attributes)
            : $order->reviewPhoto()->create($attributes);

        return back()->with('status', 'Foto ulasan pesanan #'.$order->order_number.' disimpan.');
    }

    /** Removes the review for good: the stars, the words and the photo. The order itself stays. */
    public function destroy(Order $order): RedirectResponse
    {
        abort_unless($order->hasReview(), 404);

        DB::transaction(function () use ($order) {
            $order->reviewPhoto()->delete();
            $order->update(['rating' => null, 'review_text' => null, 'reviewed_at' => null, 'review_hidden_at' => null]);
        });

        return back()->with('status', 'Ulasan pesanan #'.$order->order_number.' dihapus.');
    }
}
