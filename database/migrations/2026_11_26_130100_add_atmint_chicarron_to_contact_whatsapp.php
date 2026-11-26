<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Adds "Atmint Chicarron" to the admins the checkout chat offers. The list lives in the site settings (the
 * superadmin edits it under Contact), so a deploy has to append to what is stored. A shop that has not set any
 * admin yet is left alone, and running it twice adds nothing.
 */
return new class extends Migration
{
    private const ENTRY = 'Atmint Chicarron: +62 896-0275-1180';

    public function up(): void
    {
        $stored = trim((string) DB::table('site_settings')->where('key', 'contact_whatsapp')->value('value'));

        if ($stored === '' || str_contains(preg_replace('/\D+/', '', $stored) ?? '', '89602751180')) {
            return;
        }

        DB::table('site_settings')->where('key', 'contact_whatsapp')->update([
            'value' => $stored.', '.self::ENTRY,
            'updated_at' => now(),
        ]);

        Cache::forget('site-settings');
    }

    public function down(): void
    {
        $stored = (string) DB::table('site_settings')->where('key', 'contact_whatsapp')->value('value');
        $without = trim((string) preg_replace('/,?\s*'.preg_quote(self::ENTRY, '/').'/', '', $stored), ', ');

        if ($without !== trim($stored)) {
            DB::table('site_settings')->where('key', 'contact_whatsapp')->update(['value' => $without, 'updated_at' => now()]);
            Cache::forget('site-settings');
        }
    }
};
