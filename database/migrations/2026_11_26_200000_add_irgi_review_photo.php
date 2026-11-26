<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Irgi's review had words only; he sent a photo to go with it. The words stay as they are and the picture
 * (database/data/irgi-review.webp, scaled to 960 x 1280) is stored the way the review form stores one, so the
 * review moves from the order slips to the polaroids on Home and /reviews.
 */
return new class extends Migration
{
    public function up(): void
    {
        $order = DB::table('orders')
            ->whereRaw('LOWER(customer_name) LIKE ?', ['%irgi%'])
            ->whereNotNull('rating')
            ->orderByDesc('reviewed_at')
            ->first();

        $file = database_path('data/irgi-review.webp');

        if (! $order || ! is_file($file)) {
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
        // The photo is real content now; rolling back leaves it in place.
    }
};
