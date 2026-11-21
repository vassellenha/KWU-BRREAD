<?php

namespace App\Http\Requests\Staff;

use App\Rules\PersonName;
use App\Rules\PhoneNumber;

/**
 * "Save all" on the HR page: details, rotation order and active state of every changed employee.
 */
class BulkHrRequest extends BulkRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function rowRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new PersonName],
            'position' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', new PhoneNumber],
            'rotation_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
