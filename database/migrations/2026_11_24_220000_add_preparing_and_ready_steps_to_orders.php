<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The tracking page now has four steps: Masuk, Pembayaran berhasil, Pesanan disiapkan, Pesanan siap diambil.
 * Staff move an order through them by hand, so the old "completed" (the customer pressed Pesanan Diterima) becomes
 * "ready", and "received_at" turns into "ready_at". A new "preparing_at" keeps when the order started being prepared
 * ("processing" was already an allowed status). PostgreSQL keeps a CHECK on the status values, so it has to learn "ready".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('preparing_at')->nullable()->after('paid_at');
            $table->renameColumn('received_at', 'ready_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;');
        }

        DB::table('orders')->where('status', 'completed')->update(['status' => 'ready']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('pending', 'confirmed', 'paid', 'processing', 'ready', 'cancelled'));");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;');
        }

        DB::table('orders')->where('status', 'ready')->update(['status' => 'completed']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('pending', 'confirmed', 'paid', 'processing', 'completed', 'cancelled'));");
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('ready_at', 'received_at');
            $table->dropColumn('preparing_at');
        });
    }
};
