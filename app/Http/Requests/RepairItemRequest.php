<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RepairItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('repair_form.edit') && $this->user()->can('item.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Unique: a line is claimed by exactly one piece coming back.
            'repair_form_line_id' => [
                'required', 'integer',
                Rule::exists('repair_form_lines', 'id'),
                Rule::unique('items', 'repair_form_line_id')->withoutTrashed(),
            ],

            'metal_type_id' => ['required', 'exists:metal_types,id'],
            'purity_id' => ['required', 'exists:purities,id'],

            'gross_weight' => ['required', 'numeric', 'min:0.001', 'max:999999.999'],
            // Anything the piece lost to polish or wax lives in the gap between the
            // two, which is stored as other_deduction.
            'net_weight' => ['required', 'numeric', 'min:0', 'max:999999.999', 'lte:gross_weight'],

            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],

            'extra_charge_1' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'extra_charge_1_label' => ['nullable', 'string', 'max:20'],
            'extra_charge_2' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'extra_charge_2_label' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'repair_form_line_id.unique' => 'That line already has a piece booked into stock.',
            'net_weight.lte' => 'Net weight cannot be more than the gross weight.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Both columns are NOT NULL with a zero default, so a blank box means zero
        // rather than null.
        $this->merge([
            'extra_charge_1' => $this->input('extra_charge_1') ?: 0,
            'extra_charge_2' => $this->input('extra_charge_2') ?: 0,
        ]);
    }
}
