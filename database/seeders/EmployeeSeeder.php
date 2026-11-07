<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

/**
 * Runs on every deploy, so it only fills an empty table and never undoes changes made in HR.
 */
class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        if (Employee::exists()) {
            return;
        }

        $employees = [
            'Rizky Joestar',
            'Evan Brando',
            'Vassell Speedwagon',
            'Jordan Yoshikage',
            'Chandra Liaow',
            'Gofar Hilman',
        ];

        foreach ($employees as $index => $name) {
            Employee::updateOrCreate(
                ['name' => $name],
                ['position' => 'Store Crew', 'rotation_order' => $index + 1, 'is_active' => true],
            );
        }
    }
}
