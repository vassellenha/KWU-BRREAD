<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

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
            ->with('reviewPhoto:id,order_id,width,height,updated_at')
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
            'stats' => [
                'total' => (clone $reviews)->count(),
                'average' => round((float) (clone $reviews)->avg('rating'), 1),
                'hidden' => (clone $reviews)->whereNotNull('review_hidden_at')->count(),
                'photos' => (clone $reviews)->whereHas('reviewPhoto')->count(),
                'fresh' => (clone $reviews)->where('reviewed_at', '>=', now()->subDay())->count(),
            ],
        ]);
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
