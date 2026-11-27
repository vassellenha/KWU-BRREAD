<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The WhatsApp admins Atmint Rusdi and Atmint Gatot get an employee record, next to Atmint Amba, so the Active
 * toggle in HR can switch their chat button off (see App\Support\ContactInfo).
 */
return new class extends Migration
{
    public function up(): void
    {
        $next = ((int) DB::table('employees')->max('rotation_order')) + 1;

        foreach ([['Atmint Rusdi', '+62 811-6362-805'], ['Atmint Gatot', '+62 896-0275-1180']] as [$name, $phone]) {
            if (DB::table('employees')->whereRaw('LOWER(name) = ?', [strtolower($name)])->exists()) {
                continue;
            }

            DB::table('employees')->insert([
                'name' => $name,
                'position' => 'Customer Service',
                'phone' => $phone,
                'rotation_order' => $next++,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('employees')->whereIn('name', ['Atmint Rusdi', 'Atmint Gatot'])->delete();
    }
};
