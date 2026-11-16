<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The seeder only fills an empty table, so deployments that already hold the placeholder
     * crew need this one-time swap to the real team. Anyone added by hand in HR stays, after
     * the team in rotation order.
     */
    public function up(): void
    {
        // A fresh database has no crew to swap; the seeder fills it with the real team.
        if (! DB::table('employees')->exists()) {
            return;
        }

        $placeholders = ['Rizky Joestar', 'Evan Brando', 'Vassell Speedwagon', 'Jordan Yoshikage', 'Chandra Liaow', 'Gofar Hilman'];

        $team = [
            ['Rizky Sandi Pratama', 'Ketua'],
            ['Vassell Enha Atthayya', 'Anggota'],
            ['Evan Octavianus Haryo Handoko', 'Anggota'],
            ['Muhammad Jordan', 'Anggota'],
            ['Bhisma Abimanyu Putra Setiawan', 'Anggota'],
            ['Dhimas Aqillah Syah', 'Anggota'],
            ['Herlandito Ibnu Chandra', 'Anggota'],
            ['Muhammad Rafa Irhamsyah', 'Anggota'],
            ['Rafy Aderahma Putra', 'Anggota'],
        ];

        DB::table('employees')->whereIn('name', $placeholders)->delete();

        foreach ($team as $index => [$name, $position]) {
            $values = ['position' => $position, 'rotation_order' => $index + 1, 'is_active' => true, 'updated_at' => now()];

            if (DB::table('employees')->where('name', $name)->exists()) {
                DB::table('employees')->where('name', $name)->update($values);
            } else {
                DB::table('employees')->insert($values + ['name' => $name, 'created_at' => now()]);
            }
        }

        $next = count($team) + 1;

        DB::table('employees')
            ->whereNotIn('name', array_column($team, 0))
            ->orderBy('rotation_order')
            ->orderBy('id')
            ->pluck('id')
            ->each(function ($id) use (&$next) {
                DB::table('employees')->where('id', $id)->update(['rotation_order' => $next++]);
            });
    }

    public function down(): void
    {
        // The placeholder crew is gone for good; there is nothing worth restoring.
    }
};
