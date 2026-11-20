<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every successful sign-in to the staff portal or the superadmin panel: who, from where, with what.
 * The superadmin reads it under Security, Sign-ins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sign_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('username', 60)->nullable();
            $table->string('name', 120)->nullable();
            $table->string('role', 30)->nullable();
            $table->string('portal', 20);
            $table->string('ip', 45)->nullable();
            $table->string('browser', 60)->nullable();
            $table->string('os', 60)->nullable();
            $table->string('device', 40)->nullable();
            $table->string('country', 80)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('isp', 120)->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        if (DB::getDriverName() === 'pgsql' && str_contains((string) config('database.connections.pgsql.host'), 'supabase')) {
            DB::statement('alter table "public"."sign_ins" enable row level security');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sign_ins');
    }
};
