<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->unsignedInteger('stock_quantity')->default(0)->after('hex_color');
            $table->string('unit')->default('pcs')->after('stock_quantity');
            $table->unsignedInteger('reorder_point')->default(0)->after('unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->dropColumn(['stock_quantity', 'unit', 'reorder_point']);
        });
    }
};
