<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A last step after "ready": the order is done ("selesai"). Staff can mark it, and the customer can finish it from the
 * tracking page once they left a rating and a comment. PostgreSQL keeps a CHECK on the status values, so it learns
 * "completed" here. The orders of Irgi and Fadking are already picked up, so they are closed right away.
 */
return new class extends Migration
{
    private const DONE_CUSTOMERS = ['irgi', 'fadking'];

    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('ready_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('pending', 'confirmed', 'paid', 'processing', 'ready', 'completed', 'cancelled'));");
        }

        DB::table('orders')
            ->where('status', 'ready')
            ->where(function ($query) {
                foreach (self::DONE_CUSTOMERS as $name) {
                    $query->orWhereRaw('LOWER(customer_name) LIKE ?', ['%'.$name.'%']);
                }
            })
            ->update(['status' => 'completed', 'completed_at' => now()]);
    }

    public function down(): void
    {
        DB::table('orders')->where('status', 'completed')->update(['status' => 'ready']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('pending', 'confirmed', 'paid', 'processing', 'ready', 'cancelled'));");
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
