<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Atmint Chicarron's checkout message is written in old-fashioned English: this adds the "(kuno)" style to the entry
 * already stored in the site settings. An entry that already has a style, or a shop without Chicarron, is left alone.
 */
return new class extends Migration
{
    private const ENTRY = 'Atmint Chicarron: +62 896-0275-1180';

    public function up(): void
    {
        $stored = (string) DB::table('site_settings')->where('key', 'contact_whatsapp')->value('value');
        $updated = preg_replace('/'.preg_quote(self::ENTRY, '/').'(?!\s*\()/', self::ENTRY.' (kuno)', $stored);

        if ($updated !== null && $updated !== $stored) {
            DB::table('site_settings')->where('key', 'contact_whatsapp')->update(['value' => $updated, 'updated_at' => now()]);
            Cache::forget('site-settings');
        }
    }

    public function down(): void
    {
        $stored = (string) DB::table('site_settings')->where('key', 'contact_whatsapp')->value('value');
        $updated = str_replace(self::ENTRY.' (kuno)', self::ENTRY, $stored);

        if ($updated !== $stored) {
            DB::table('site_settings')->where('key', 'contact_whatsapp')->update(['value' => $updated, 'updated_at' => now()]);
            Cache::forget('site-settings');
        }
    }
};
