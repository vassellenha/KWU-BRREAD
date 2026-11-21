<?php

namespace App\Http\Requests\Staff;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for the staff "save all" endpoints. The page sends only the rows that changed, keyed by record id:
 * rows[12][price]=9000. Errors therefore come back keyed rows.12.price, which the page maps onto that cell.
 * The module's role middleware already guards the route.
 */
abstract class BulkRequest extends FormRequest
{
    public const MAX_ROWS = 200;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules for one row, keyed by the field name inside the row.
     *
     * @return array<string, list<mixed>>
     */
    abstract protected function rowRules(): array;

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        $rules = [
            'rows' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS],
            'rows.*.version' => ['required', 'string', 'max:64'],
        ];

        foreach ($this->rowRules() as $field => $fieldRules) {
            $rules["rows.*.{$field}"] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Readable field names, so a message says "price" and not "rows.12.price".
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (array_keys((array) $this->input('rows', [])) as $id) {
            foreach (array_keys($this->rowRules()) as $field) {
                $attributes["rows.{$id}.{$field}"] = str_replace('_', ' ', $field);
            }
        }

        return $attributes;
    }

    /**
     * Row keys must be record ids.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys((array) $this->input('rows', [])) as $key) {
                if (! is_int($key) && ! ctype_digit((string) $key)) {
                    $validator->errors()->add('rows', 'One of the rows is not valid. Reload the page and try again.');
                    break;
                }
            }
        });
    }

    /**
     * The changed rows, keyed by integer id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function changedRows(): array
    {
        $rows = [];

        foreach ((array) $this->validated('rows') as $id => $row) {
            $rows[(int) $id] = $row;
        }

        return $rows;
    }
}
