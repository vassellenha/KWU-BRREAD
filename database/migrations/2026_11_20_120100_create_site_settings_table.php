<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Small values the superadmin can change from the panel without a deploy, such as the shop's contact person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql' && str_contains((string) config('database.connections.pgsql.host'), 'supabase')) {
            DB::statement('alter table "public"."site_settings" enable row level security');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
