<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A review can carry more than one photo: review_photos.order_id stops being unique.
 * A plain index replaces it first, because the foreign key leaned on the unique one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_photos', function (Blueprint $table) {
            $table->index('order_id', 'review_photos_order_id_index');
        });

        Schema::table('review_photos', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
        });
    }

    public function down(): void
    {
        // Several photos per order cannot fit a unique index again: keep the oldest of each, then restore it.
        $keep = \Illuminate\Support\Facades\DB::table('review_photos')->selectRaw('MIN(id) as id')->groupBy('order_id')->pluck('id');
        \Illuminate\Support\Facades\DB::table('review_photos')->whereNotIn('id', $keep)->delete();

        Schema::table('review_photos', function (Blueprint $table) {
            $table->unique('order_id');
        });

        Schema::table('review_photos', function (Blueprint $table) {
            $table->dropIndex('review_photos_order_id_index');
        });
    }
};
