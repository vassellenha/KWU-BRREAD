<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The original orders table limits status to pending/processing/completed. The confirmation
 * migration widened that list on PostgreSQL only, so on SQLite and MySQL every Confirm and
 * Cancel click in the staff portal failed with a constraint error. Those drivers get a plain
 * string column here; PostgreSQL keeps the CHECK constraint the earlier migration set up.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->change();
        });
    }

    public function down(): void
    {
        // Narrowing the column again would reject confirmed and cancelled rows; nothing to undo.
    }
};
