<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhoneNumberList implements ValidationRule
{
    public const MAX_NUMBERS = 3;

    /**
     * One to three admins separated by commas, each "Name: number" or just a number, the number a valid PhoneNumber.
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

            (new PhoneNumber)->validate($attribute, trim($number), $fail);
        }
    }
}
