<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per lockdown trigger: who hammered the sign-in forms, from where and with what.
 * Repeats from the same address within a minute bump `hits` instead of adding rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->string('reason', 20);
            $table->string('ip', 45)->index();
            $table->string('username', 40)->nullable();
            $table->string('path', 120)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('browser', 60)->nullable();
            $table->string('os', 60)->nullable();
            $table->string('device', 60)->nullable();
            $table->string('country', 80)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('isp', 120)->nullable();
            $table->unsignedInteger('hits')->default(1);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        // Same reason as the earlier RLS migration: keep Supabase's public API out of this table.
        if (DB::getDriverName() === 'pgsql' && str_contains((string) config('database.connections.pgsql.host'), 'supabase')) {
            DB::statement('alter table "public"."security_events" enable row level security');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
