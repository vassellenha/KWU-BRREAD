<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['username' => 'admin', 'name' => 'Brread! Admin', 'email' => 'admin@brread.id', 'password' => 'brread-admin-2026', 'role' => User::ROLE_ADMIN],
            ['username' => 'staffmrp', 'name' => 'MRP Staff', 'email' => 'staffmrp@brread.id', 'password' => '12345678', 'role' => User::ROLE_WAREHOUSE],
            ['username' => 'staffhr', 'name' => 'HR Staff', 'email' => 'staffhr@brread.id', 'password' => '12345678', 'role' => User::ROLE_HR],
            ['username' => 'staffacc', 'name' => 'Accounting Staff', 'email' => 'staffacc@brread.id', 'password' => '12345678', 'role' => User::ROLE_ACCOUNTANT],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['username' => $account['username']],
                $account + ['email_verified_at' => now()],
            );
        }
    }
}
