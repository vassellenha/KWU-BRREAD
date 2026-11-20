<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * Everything about the shop a customer reads on the site that the superadmin can change under Website in the
 * panel: where the counter is, its hours, whether orders are being taken, the delivery promises and the Home
 * promo line. Until a value is saved there, the value the site launched with is used.
 */
class ShopSettings
{
    public const DEFAULTS = [
        'address_short' => 'Minangkabau, Tel-U',
        'address_lines' => "Telkom University, Kampus C\nJl. Minangkabau Barat No. 50\nSetiabudi, Jakarta Selatan",
        'maps_query' => 'Telkom University Jakarta Kampus C Minangkabau, Jl. Minangkabau Barat No. 50, Pasar Manggis, Setiabudi, Jakarta Selatan',
        'hours_open' => '11:00',
        'hours_close' => '21:30',
        'counter_closed' => '0',
        'counter_closed_note' => '',
        'orders_open' => '1',
        'orders_paused_message' => 'We are not taking orders right now. Back soon!',
        'delivery_minutes' => '20',
        'frozen_minutes' => '60',
        'promo_text' => 'to win a monthly Rp 500.000 ice cream allowance.',
    ];

    /**
     * @return array{address_short: string, address_lines: list<string>, address_one_line: string, maps_query: string, maps_place_url: string, maps_directions_url: string, hours_open: string, hours_close: string, open_minutes: int, close_minutes: int, open_label: string, close_label: string, hours_label: string, counter_closed: bool, counter_closed_note: string, orders_open: bool, orders_paused_message: string, delivery_minutes: int, frozen_minutes: int, promo_text: string}
     */
    public static function get(): array
    {
        static $memo = null;
        if ($memo !== null && ! app()->runningUnitTests()) {
            return $memo;
        }

        $stored = SiteSetting::allValues();
        $value = fn (string $key): string => array_key_exists($key, $stored) && $stored[$key] !== null && trim((string) $stored[$key]) !== ''
            ? trim((string) $stored[$key])
            : self::DEFAULTS[$key];
        // a switch can be saved as "0", which the empty check above must not replace with its default
        $flag = fn (string $key): bool => (array_key_exists($key, $stored) ? (string) $stored[$key] : self::DEFAULTS[$key]) === '1';

        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $value('address_lines')) ?: []), fn ($line) => $line !== ''));
        $mapsQuery = $value('maps_query');
        $open = self::minutes($value('hours_open'), 11 * 60);
        $close = self::minutes($value('hours_close'), 21 * 60 + 30);

        return $memo = [
            'address_short' => $value('address_short'),
            'address_lines' => $lines,
            'address_one_line' => implode(', ', $lines),
            'maps_query' => $mapsQuery,
            // the launch place link pointed at the exact listing; once the address changes, a search for it is used
            'maps_place_url' => $mapsQuery === self::DEFAULTS['maps_query'] && config('services.google_maps.place_url')
                ? (string) config('services.google_maps.place_url')
                : 'https://www.google.com/maps/search/?api=1&query='.urlencode($mapsQuery),
            'maps_directions_url' => 'https://www.google.com/maps/dir/?api=1&destination='.urlencode($mapsQuery),
            'hours_open' => sprintf('%02d:%02d', intdiv($open, 60), $open % 60),
            'hours_close' => sprintf('%02d:%02d', intdiv($close, 60), $close % 60),
            'open_minutes' => $open,
            'close_minutes' => $close,
            'open_label' => self::label($open),
            'close_label' => self::label($close),
            'hours_label' => 'Daily, '.self::label($open).' to '.self::label($close),
            'counter_closed' => $flag('counter_closed'),
            'counter_closed_note' => $value('counter_closed_note'),
            'orders_open' => $flag('orders_open'),
            'orders_paused_message' => $value('orders_paused_message'),
            'delivery_minutes' => max(1, (int) $value('delivery_minutes')),
            'frozen_minutes' => max(1, (int) $value('frozen_minutes')),
            'promo_text' => $value('promo_text'),
        ];
    }

    /** "21:30" as minutes after midnight. */
    private static function minutes(string $time, int $fallback): int
    {
        return preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m) && (int) $m[1] < 24 && (int) $m[2] < 60
            ? (int) $m[1] * 60 + (int) $m[2]
            : $fallback;
    }

    /** 690 becomes "11:30 AM", 1260 becomes "9 PM". */
    public static function label(int $minutes): string
    {
        $h = intdiv($minutes, 60) % 24;
        $m = $minutes % 60;
        $suffix = $h < 12 ? 'AM' : 'PM';
        $h12 = $h % 12 === 0 ? 12 : $h % 12;

        return $h12.($m ? sprintf(':%02d', $m) : ':00').' '.$suffix;
    }
}
