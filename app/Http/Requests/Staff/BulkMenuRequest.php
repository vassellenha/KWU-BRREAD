<?php

namespace App\Http\Requests\Staff;

use App\Http\Controllers\Staff\MenuController;

/**
 * "Save all" on the menu page: name, price, flavor color and availability of every changed item.
 * Rules that depend on the item (category, a name already taken) are checked in MenuController::bulkUpdate.
 */
class BulkMenuRequest extends BulkRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function rowRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60', MenuController::NAME_PATTERN],
            'price' => ['required', 'integer', 'min:0', 'max:'.MenuController::MAX_PRICE],
            'hex_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
