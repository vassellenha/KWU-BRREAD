<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reviews now show on the public pages. The superadmin can keep one off them (spam, a test, something rude)
 * without deleting it: the rating stays in the reports and on the customer's own order page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('review_hidden_at')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('review_hidden_at');
        });
    }
};
