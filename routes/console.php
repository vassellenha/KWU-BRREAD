<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Way back in if an address was blocked by mistake (including your own).
Artisan::command('security:unblock {ip}', function (string $ip) {
    $deleted = \App\Models\IpBlock::where('ip', $ip)->first()?->delete();
    $deleted ? $this->info("{$ip} is unblocked.") : $this->warn("{$ip} was not blocked.");
})->purpose('Lift a block the superadmin placed on an address');
