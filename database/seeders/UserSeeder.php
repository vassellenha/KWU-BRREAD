<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Creates the four portal accounts if they do not exist yet. An existing account is left
     * alone, so a password changed in production survives the next deploy (the entrypoint
     * seeds on every start). Each account reads its own variable (ADMIN_PASSWORD for the superadmin,
     * STAFFMRP_PASSWORD, STAFFHR_PASSWORD, STAFFACC_PASSWORD) and falls back to the shared
     * STAFF_PASSWORD for staff. Setting one also resets that account's password on purpose,
     * which is how to rotate it.
     * Without them, local environments get the documented dev defaults and production gets
     * random passwords, printed once in the deploy log, to be changed right away.
     */
    public function run(): void
    {
        $accounts = [
            ['username' => 'superadmin', 'name' => 'Brread! Superadmin', 'email' => 'admin@brread.id', 'role' => User::ROLE_ADMIN, 'group' => 'ADMIN', 'env' => ['SUPERADMIN_PASSWORD', 'ADMIN_PASSWORD']],
            ['username' => 'staffmrp', 'name' => 'MRP Staff', 'email' => 'staffmrp@brread.id', 'role' => User::ROLE_WAREHOUSE, 'group' => 'STAFF'],
            ['username' => 'staffhr', 'name' => 'HR Staff', 'email' => 'staffhr@brread.id', 'role' => User::ROLE_HR, 'group' => 'STAFF'],
            ['username' => 'staffacc', 'name' => 'Accounting Staff', 'email' => 'staffacc@brread.id', 'role' => User::ROLE_ACCOUNTANT, 'group' => 'STAFF'],
        ];

        foreach ($accounts as $account) {
            $group = $account['group'];
            $envKeys = $account['env'] ?? [strtoupper($account['username']).'_PASSWORD', $group.'_PASSWORD'];
            unset($account['group'], $account['env']);

            $configured = collect($envKeys)->map(fn (string $key) => env($key))->first(fn ($value) => filled($value));
            $existing = User::where('username', $account['username'])->first();

            if ($existing && ! $configured) {
                continue;
            }

            $generated = null;
            $password = $configured;

            if (! $password) {
                $password = app()->isProduction()
                    ? ($generated = Str::password(16, symbols: false))
                    : ($group === 'ADMIN' ? 'brread-admin-2026' : '12345678');
            }

            User::updateOrCreate(
                ['username' => $account['username']],
                $account + ['password' => $password, 'email_verified_at' => now()],
            );

            if ($generated) {
                $this->command?->warn("Created {$account['username']} with password {$generated} - change it now.");
            }
        }
    }
}
