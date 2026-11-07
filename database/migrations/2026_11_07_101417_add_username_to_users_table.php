<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Existing accounts keep their id (stock movements and expenses point at it),
     * so they are renamed in place instead of being recreated by the seeder.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('name');
        });

        $renamedAccounts = [
            'warehouse@brread.id' => 'staffmrp',
            'hr@brread.id' => 'staffhr',
            'accounting@brread.id' => 'staffacc',
            'admin@brread.id' => 'admin',
        ];

        DB::table('users')->orderBy('id')->each(function (object $user) use ($renamedAccounts) {
            DB::table('users')->where('id', $user->id)->update([
                'username' => $renamedAccounts[$user->email] ?? "user{$user->id}",
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
