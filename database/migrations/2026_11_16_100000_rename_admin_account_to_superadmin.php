<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The admin account is now called superadmin. Renaming the row in place keeps its password
     * and its history, so nobody has to reset anything after the deploy.
     */
    public function up(): void
    {
        if (DB::table('users')->where('username', 'superadmin')->exists()) {
            return;
        }

        DB::table('users')->where('username', 'admin')->where('role', 'admin')->update(['username' => 'superadmin']);
    }

    public function down(): void
    {
        if (DB::table('users')->where('username', 'admin')->exists()) {
            return;
        }

        DB::table('users')->where('username', 'superadmin')->where('role', 'admin')->update(['username' => 'admin']);
    }
};
