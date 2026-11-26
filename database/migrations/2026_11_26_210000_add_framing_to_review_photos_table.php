<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a review photo sits in its frame on the public pages, set by the superadmin in Reviews. "auto" fills the frame
 * when the photo is close to its shape and otherwise shows the whole photo on the Brread! sprinkle backdrop; "cover"
 * always fills it, "contain" always shows the whole photo. Zoom is a percentage, the focus point is a percentage of
 * the photo (where the frame is centred when it has to crop).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_photos', function (Blueprint $table) {
            $table->string('fit', 8)->default('auto');
            $table->unsignedTinyInteger('focus_x')->default(50);
            $table->unsignedTinyInteger('focus_y')->default(50);
            $table->unsignedSmallInteger('zoom')->default(100);
        });
    }

    public function down(): void
    {
        Schema::table('review_photos', function (Blueprint $table) {
            $table->dropColumn(['fit', 'focus_x', 'focus_y', 'zoom']);
        });
    }
};
