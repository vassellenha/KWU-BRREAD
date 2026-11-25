<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The creations of an order as the staff's edit dialog sends them. The route's role middleware decides who may edit;
 * the preview (POST) needs only the creations, the save (PUT) also the version it was opened on and the reason.
 */
class UpdateOrderItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'items' => ['required', 'list', 'min:1', 'max:20'],
            'items.*' => ['required', 'array'],
            'items.*.index' => ['required', 'integer', 'min:0'],
            'items.*.component_ids' => ['required', 'list', 'min:2', 'max:10'],
            'items.*.component_ids.*' => ['integer', 'exists:components,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ];

        if ($this->isMethod('PUT')) {
            $rules['version'] = ['required', 'string', 'max:64'];
            $rules['reason'] = ['required', 'string', 'min:3', 'max:300'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Tulis alasan perubahan, misalnya "Rasa Neapolitan kosong, diganti Matcha via WA".',
            'reason.min' => 'Alasan perubahan terlalu singkat.',
            'items.*.quantity.max' => 'Satu kreasi maksimal 20.',
        ];
    }
}
