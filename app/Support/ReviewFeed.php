<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Real customer reviews for the public pages. A review with a photo is a polaroid (the three newest on Home, up to
 * thirty on /reviews, and the photo wall on About); a review with only words goes in the list under them.
 * Names are shortened to a first name and an initial, and no phone number or order detail leaves this class.
 * A review the superadmin hid (Website in the panel) never shows here.
 */
class ReviewFeed
{
    public const WALL_MAX = 30;

    /**
     * The newest reviews that came with a photo.
     *
     * @return Collection<int, array{name: string, rating: int, quote: string, made: string, photo: string, width: int, height: int, frame: string, when: string}>
     */
    public static function photos(int $limit = 3): Collection
    {
        return self::safely(fn () => Order::query()
            ->whereNotNull('rating')
            ->whereNull('review_hidden_at')
            ->whereHas('reviewPhoto')
            ->with(['reviewPhoto:id,order_id,width,height,updated_at,fit,focus_x,focus_y,zoom', 'orderItems.component'])
            ->latest('reviewed_at')
            ->limit($limit)
            ->get()
            ->map(fn (Order $order) => self::card($order) + [
                'photo' => $order->reviewPhoto->url(),
                'width' => $order->reviewPhoto->width,
                'height' => $order->reviewPhoto->height,
                'frame' => $order->reviewPhoto->frameStyle(),
            ]));
    }

    /**
     * The newest reviews that came with words but no photo.
     *
     * @return Collection<int, array{name: string, rating: int, quote: string, made: string, when: string}>
     */
    public static function words(int $limit = 6): Collection
    {
        return self::safely(fn () => Order::query()
            ->whereNotNull('rating')
            ->whereNull('review_hidden_at')
            ->whereNotNull('review_text')
            ->where('review_text', '!=', '')
            ->whereDoesntHave('reviewPhoto')
            ->with('orderItems.component')
            ->latest('reviewed_at')
            ->limit($limit)
            ->get()
            ->map(fn (Order $order) => self::card($order)));
    }

    /**
     * @return array{name: string, rating: int, quote: string, made: string, when: string}
     */
    private static function card(Order $order): array
    {
        return [
            'name' => self::shortName((string) $order->customer_name),
            'rating' => (int) $order->rating,
            'quote' => (string) $order->review_text,
            'made' => self::made($order),
            'when' => $order->reviewed_at?->diffForHumans() ?? '',
        ];
    }

    /** "Muhammad Jordan Saputra" becomes "Muhammad J.", "rizky" becomes "Rizky". */
    public static function shortName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $parts = array_values(array_filter($parts, fn ($part) => $part !== ''));
        if (! $parts) {
            return 'A Brread! fan';
        }
        $first = mb_convert_case(mb_substr($parts[0], 0, 18), MB_CASE_TITLE);

        return count($parts) > 1 ? $first.' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.' : $first;
    }

    /** What the first sandwich of the order was, in the words the menu uses: "Whole Wheat Vanilla + Sprinkles". */
    private static function made(Order $order): string
    {
        $items = $order->orderItems->where('creation_index', $order->orderItems->min('creation_index'));
        $pick = fn (string $category) => $items->first(fn ($item) => $item->component?->category === $category)?->component?->name;
        $bread = $pick('bread');
        $bread = $bread ? trim(str_ireplace(['bread', 'plain'], '', $bread)) : null;
        $made = trim(($bread ?? '').' '.($pick('ice_cream') ?? ''));
        $topping = $pick('topping');

        return trim($made.($topping ? ' + '.$topping : '')) ?: 'A custom sandwich';
    }

    /**
     * @param  callable(): Collection<int, mixed>  $query
     * @return Collection<int, mixed>
     */
    private static function safely(callable $query): Collection
    {
        try {
            return $query();
        } catch (Throwable) {
            return collect();
        }
    }
}
