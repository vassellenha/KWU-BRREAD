<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\SiteSetting;
use Throwable;

/**
 * The shop's contact person and channels, as the superadmin last set them under Contact in the panel. Until
 * something is saved there, the values the site launched with are used.
 */
class ContactInfo
{
    public const DEFAULTS = [
        'contact_name' => '',
        'contact_phone' => '+62 21 7250 1234',
        'contact_whatsapp' => '',
        'instagram_handle' => 'brread.official',
    ];

    /**
     * @return array{name: string, phone: string, phone_href: string, whatsapp: string, whatsapp_url: ?string, whatsapp_links: list<array{name: string, number: string, url: string, style: string}>, instagram: string, instagram_url: string}
     */
    public static function get(): array
    {
        $stored = SiteSetting::allValues();
        $value = fn (string $key): string => trim((string) ($stored[$key] ?? '')) !== '' ? trim((string) $stored[$key]) : self::DEFAULTS[$key];

        $phone = $value('contact_phone');
        $whatsapp = $value('contact_whatsapp');
        $instagram = ltrim($value('instagram_handle') ?: (string) config('services.instagram.handle'), '@');

        $links = [];
        $off = self::switchedOff();
        // "Atmint Amba: 0821 3601 9367, Atmint Sandi: 0811 6362 805" (the name is optional).
        foreach (explode(',', $whatsapp) as $entry) {
            [$name, $number] = array_pad(explode(':', $entry, 2), -2, '');
            $number = trim($number);
            $style = '';
            if (preg_match('/\(([^)]*)\)\s*$/', $number, $match)) {
                $style = in_array(strtolower(trim($match[1])), ContactStyles::ALL, true) ? strtolower(trim($match[1])) : '';
                $number = trim(preg_replace('/\([^)]*\)\s*$/', '', $number));
            }
            // An admin whose HR record is switched off (Active toggle in HR) is not offered for chat any more.
            if ($number !== '' && ! in_array(self::key($name), $off, true)) {
                $links[] = [
                    'name' => trim($name) !== '' ? trim($name) : $number,
                    'number' => $number,
                    'url' => 'https://wa.me/'.ltrim(self::international($number), '+'),
                    'style' => $style,
                ];
            }
        }

        return [
            'name' => $value('contact_name'),
            'phone' => $phone,
            'phone_href' => 'tel:'.self::international($phone, keepPlus: true),
            'whatsapp' => $whatsapp,
            'whatsapp_url' => $links[0]['url'] ?? null,
            'whatsapp_links' => $links,
            'instagram' => $instagram,
            'instagram_url' => 'https://www.instagram.com/'.rawurlencode($instagram).'/',
        ];
    }

    /**
     * The WhatsApp admins whose employee record in HR is switched off. An admin is matched to an employee by name,
     * ignoring case and any note in brackets ("Atmint Gatot (Aura++)" is "Atmint Gatot"); one with no record in HR
     * is always offered, and so is one that has at least one active record.
     *
     * @return list<string>
     */
    private static function switchedOff(): array
    {
        try {
            $states = [];
            foreach (Employee::query()->get(['name', 'is_active']) as $employee) {
                $key = self::key($employee->name);
                $states[$key] = ($states[$key] ?? false) || $employee->is_active;
            }

            return array_keys(array_filter($states, fn (bool $active) => ! $active));
        } catch (Throwable) {
            return [];
        }
    }

    /** "Atmint Gatot (Aura++)" and "atmint gatot" are the same admin. */
    private static function key(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $name) ?? $name));
    }

    /** An Indonesian number in international form: 0812... and 812... both become +62812... */
    public static function international(string $number, bool $keepPlus = true): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return ($keepPlus ? '+' : '').$digits;
    }
}
