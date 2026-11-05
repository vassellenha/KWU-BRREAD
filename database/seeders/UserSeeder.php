<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@brread.id'],
            [
                'name' => 'Brread! Admin',
                'password' => 'brread-admin-2026',
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'warehouse@brread.id'],
            [
                'name' => 'Warehouse Staff',
                'password' => 'brread-warehouse-2026',
                'role' => User::ROLE_WAREHOUSE,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'hr@brread.id'],
            [
                'name' => 'HR Staff',
                'password' => 'brread-hr-2026',
                'role' => User::ROLE_HR,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'accounting@brread.id'],
            [
                'name' => 'Accounting Staff',
                'password' => 'brread-accounting-2026',
                'role' => User::ROLE_ACCOUNTANT,
                'email_verified_at' => now(),
            ]
        );
    }
}
