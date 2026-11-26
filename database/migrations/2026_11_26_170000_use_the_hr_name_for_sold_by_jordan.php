<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The sales person on an order is now picked from the people in HR, so the "Jordan" the first migration wrote
 * becomes his name as HR spells it. Nothing changes when HR has no one called Jordan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $name = DB::table('employees')->whereRaw("LOWER(name) LIKE '%jordan%'")->orderBy('rotation_order')->value('name');

        if ($name !== null && $name !== 'Jordan') {
            DB::table('orders')->where('sold_by', 'Jordan')->update(['sold_by' => $name]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the name stays as HR spells it.
    }
};
