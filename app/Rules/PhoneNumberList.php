<?php

namespace App\Rules;

use App\Support\ContactStyles;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhoneNumberList implements ValidationRule
{
    public const MAX_NUMBERS = 5;

    /**
     * One to five admins separated by commas, each "Name: number" or just a number, the number a valid PhoneNumber.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $numbers = is_string($value) ? array_values(array_filter(array_map('trim', explode(',', $value)), fn ($n) => $n !== '')) : [];

        if ($numbers === [] || count($numbers) > self::MAX_NUMBERS) {
            $fail('Enter 1 to '.self::MAX_NUMBERS.' numbers, separated by commas.');

            return;
        }

        foreach ($numbers as $entry) {
            [$label, $number] = array_pad(explode(':', $entry, 2), -2, '');

            if (mb_strlen(trim($label)) > 30) {
                $fail('Keep each name to 30 characters.');

                return;
            }

            // An optional message style in brackets after the number: "0821 3601 9367 (ceria)".
            if (preg_match('/\(([^)]*)\)\s*$/', $number, $match)) {
                if (! in_array(strtolower(trim($match[1])), ContactStyles::ALL, true)) {
                    $fail('The message style must be '.implode(' or ', ContactStyles::ALL).'.');

                    return;
                }
                $number = trim(preg_replace('/\([^)]*\)\s*$/', '', $number));
            }

            (new PhoneNumber)->validate($attribute, trim($number), $fail);
        }
    }
}
