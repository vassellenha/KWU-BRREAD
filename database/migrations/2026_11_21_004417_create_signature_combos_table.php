<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The one-click signature creations of the builder, managed by the admin in the Menu module.
 * A combo points at real menu items, so renaming a flavor never breaks it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_combos', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->foreignId('bread_id')->constrained('components')->restrictOnDelete();
            $table->foreignId('flavor_id')->constrained('components')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('signature_combo_topping', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signature_combo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topping_id')->constrained('components')->restrictOnDelete();
            $table->unique(['signature_combo_id', 'topping_id']);
        });

        if (DB::getDriverName() === 'pgsql' && str_contains((string) config('database.connections.pgsql.host'), 'supabase')) {
            DB::statement('alter table "public"."signature_combos" enable row level security');
            DB::statement('alter table "public"."signature_combo_topping" enable row level security');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_combo_topping');
        Schema::dropIfExists('signature_combos');
    }
};
