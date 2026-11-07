<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhoneNumber implements ValidationRule
{
    /**
     * Digits with an optional leading + and spaces or dashes between them, 8 to 15 digits long.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digitCount = is_string($value) ? strlen(preg_replace('/\D/', '', $value)) : 0;

        if (! is_string($value) || ! preg_match('/^\+?[0-9][0-9\s-]*$/', $value) || $digitCount < 8 || $digitCount > 15) {
            $fail('Enter a phone number using digits only, 8 to 15 digits long.');
        }
    }
}
