<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who handled the order. chat_admin is the atmint the customer chose to chat with at checkout; sold_by is the
 * sales or marketing person who brought the order in. Both stay empty until known, and staff can set either
 * one from the Orders page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('chat_admin', 80)->nullable()->after('customer_phone');
            $table->string('sold_by', 40)->nullable()->after('chat_admin');
        });

        // Every order placed on 26 Nov 2026 came in through Jordan (sales/marketing).
        $start = Carbon::parse('2026-11-26', 'Asia/Jakarta')->startOfDay();
        DB::table('orders')
            ->whereNull('sold_by')
            ->whereBetween('created_at', [$start, $start->copy()->endOfDay()])
            ->update(['sold_by' => 'Jordan']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['chat_admin', 'sold_by']);
        });
    }
};
