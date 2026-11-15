<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Supabase publishes every table in the public schema through its REST API, and the project's
 * anon key is not secret. With row level security off, anyone holding that key could read
 * users (password hashes included), orders and customer phone numbers, or write to them.
 *
 * Switching RLS on with no policies closes that door for the API roles. The Laravel app is
 * unaffected: it connects as the table owner, and owners are exempt from RLS unless FORCE is
 * set. Runs only on PostgreSQL hosted at Supabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->onSupabase()) {
            return;
        }

        foreach (DB::select("select tablename from pg_tables where schemaname = 'public'") as $table) {
            DB::statement('alter table "public"."'.str_replace('"', '""', $table->tablename).'" enable row level security');
        }
    }

    public function down(): void
    {
        // Left on deliberately: turning it off again would re-open the tables.
    }

    private function onSupabase(): bool
    {
        return DB::getDriverName() === 'pgsql'
            && str_contains((string) config('database.connections.pgsql.host'), 'supabase');
    }
};
