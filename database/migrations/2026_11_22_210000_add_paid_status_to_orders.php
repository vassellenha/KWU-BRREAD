<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Orders get a "paid" step after "confirmed": staff mark an order paid once the money is in, and the
 * tracking page shows it. PostgreSQL keeps a CHECK on the status values, so it has to learn "paid".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('confirmed_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('pending', 'confirmed', 'paid', 'processing', 'completed', 'cancelled'));");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("UPDATE orders SET status = 'confirmed' WHERE status = 'paid';");
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('pending', 'confirmed', 'processing', 'completed', 'cancelled'));");
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('paid_at');
        });
    }
};
