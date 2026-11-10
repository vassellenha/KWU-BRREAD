<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('pending', 'confirmed', 'processing', 'completed', 'cancelled'));");
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('confirmation_token', 64)->nullable()->unique()->after('status');
            $table->timestamp('confirmed_at')->nullable()->after('confirmation_token');
            $table->timestamp('cancelled_at')->nullable()->after('confirmed_at');
            $table->unsignedTinyInteger('rating')->nullable()->after('customer_phone');
            $table->text('review_text')->nullable()->after('rating');
            $table->timestamp('reviewed_at')->nullable()->after('review_text');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'confirmation_token',
                'confirmed_at',
                'cancelled_at',
                'rating',
                'review_text',
                'reviewed_at',
            ]);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('pending', 'processing', 'completed'));");
        }
    }
};

