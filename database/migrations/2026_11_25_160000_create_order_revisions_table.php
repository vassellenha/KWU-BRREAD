<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per saved edit of an order by staff: who, why, and the creations before and after (as names), plus the
 * totals. Rows are never changed or removed by the app, so the order keeps its whole history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason');
            $table->json('before');
            $table->json('after');
            $table->decimal('total_before', 8, 2);
            $table->decimal('total_after', 8, 2);
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_revisions');
    }
};
