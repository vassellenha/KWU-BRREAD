<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

/**
 * Runs on every deploy, so it only fills an empty table and never undoes changes made in HR.
 * The roster is the real team: Rizky leads, the other eight are members. Nine people make
 * three duty teams of three.
 */
class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        if (Employee::exists()) {
            return;
        }

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

        foreach ($team as $index => [$name, $position]) {
            Employee::updateOrCreate(
                ['name' => $name],
                ['position' => $position, 'rotation_order' => $index + 1, 'is_active' => true],
            );
        }
    }
}
