<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
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
