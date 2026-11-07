<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PersonName implements ValidationRule
{
    /**
     * Letters from any alphabet plus the punctuation real names use, such as O'Brien-Santoso.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || mb_strlen($value) < 2 || ! preg_match("/^\p{L}[\p{L}\s.'-]*$/u", $value)) {
            $fail('Names can only use letters, spaces, and . \' - (at least 2 characters).');
        }
    }
}
