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
    }
}
