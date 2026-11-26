<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fadking asked for a review of his own order (5 stars, his words, and the photo of him with the box). It is saved the
 * way the review form would save it, so it shows on Home and /reviews like any other. The photo ships in
 * database/data/fadking-review.webp (already scaled to 960 x 1280) and is stored as base64 in review_photos.
 */
return new class extends Migration
{
    private const TEXT = 'es krim nya muantap kali cik, jangan sampai ngiler loh ya 😹🍞🍨';

    public function up(): void
    {
        $order = DB::table('orders')
            ->whereRaw('LOWER(customer_name) LIKE ?', ['%fadking%'])
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('id')
            ->first();

        if (! $order) {
            return;
        }

        DB::table('orders')->where('id', $order->id)->update([
            'rating' => 5,
            'review_text' => self::TEXT,
            'reviewed_at' => now(),
            'review_hidden_at' => null,
        ]);

        $file = database_path('data/fadking-review.webp');
        if (! is_file($file)) {
            return;
        }

        DB::table('review_photos')->updateOrInsert(
            ['order_id' => $order->id],
            [
                'mime' => 'image/webp',
                'width' => 960,
                'height' => 1280,
                'data' => base64_encode((string) file_get_contents($file)),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        // The review is real content now; rolling back leaves it in place.
    }
};
