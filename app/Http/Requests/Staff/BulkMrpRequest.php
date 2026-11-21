<?php

namespace App\Http\Requests\Staff;

/**
 * "Save all" on the stock page: one stock in or stock out per changed ingredient, with a shared note
 * that every row without its own note falls back to.
 */
class BulkMrpRequest extends BulkRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function rowRules(): array
    {
        return [
            'direction' => ['required', 'in:in,out'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'cost' => ['nullable', 'integer', 'min:0', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'shared_note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
