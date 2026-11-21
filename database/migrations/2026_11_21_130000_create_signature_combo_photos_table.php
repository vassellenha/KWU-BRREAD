<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The photo the admin puts on a signature combo card of the Home page. Kept in the database (re-encoded and
 * downscaled, base64) because the app's own disk is wiped on every deploy, and in its own table so listing the
 * combos never drags the picture bytes along.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_combo_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signature_combo_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('mime', 20);
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->longText('data');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql' && str_contains((string) config('database.connections.pgsql.host'), 'supabase')) {
            DB::statement('alter table "public"."signature_combo_photos" enable row level security');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_combo_photos');
    }
};
