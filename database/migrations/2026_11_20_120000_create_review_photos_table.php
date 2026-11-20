<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The photo a customer can add to their review. The picture itself is kept in the database (re-encoded and
 * downscaled, base64), because the app's own disk on Railway is wiped on every deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('mime', 20);
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->longText('data');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql' && str_contains((string) config('database.connections.pgsql.host'), 'supabase')) {
            DB::statement('alter table "public"."review_photos" enable row level security');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('review_photos');
    }
};
