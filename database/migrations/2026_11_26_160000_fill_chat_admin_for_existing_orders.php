<?php

use App\Support\ContactInfo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Orders placed before the checkout recorded the atmint get theirs filled in from what the team knows: which
 * customer chatted with which atmint. The atmint names come from the contact list as saved in the panel, so the
 * stored name is used exactly as written. Orders that already have an atmint, and customers not listed here,
 * are left alone.
 */
return new class extends Migration
{
    /** Part of the atmint's name => parts of the customer names that chatted with that atmint. */
    private const CUSTOMERS_BY_ATMINT = [
        'amba' => ['faiz', 'hanif', 'tegar', 'rayfan', 'nabil', 'nopal', 'kevin'],
        'rusdi' => ['komang', 'fadking', 'irgi', 'puti'],
    ];

    public function up(): void
    {
        Cache::forget('site-settings');
        $atmints = collect(ContactInfo::get()['whatsapp_links'])->pluck('name');

        foreach (self::CUSTOMERS_BY_ATMINT as $namePart => $customers) {
            $atmint = $atmints->first(fn (string $name) => str_contains(strtolower($name), $namePart));
            if ($atmint === null) {
                continue;
            }

            foreach ($customers as $customer) {
                DB::table('orders')
                    ->whereNull('chat_admin')
                    ->whereRaw('LOWER(customer_name) LIKE ?', ['%'.$customer.'%'])
                    ->update(['chat_admin' => $atmint]);
            }
        }
    }

    public function down(): void
    {
        // Nothing to undo: the column itself is removed by the migration that created it.
    }
};
