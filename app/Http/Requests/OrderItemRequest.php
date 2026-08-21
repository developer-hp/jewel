<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('order_form.edit') && $this->user()->can('item.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Unique: one piece per line, and the line must be one that is to be made.
            'order_form_line_id' => [
                'required', 'integer',
                Rule::exists('order_form_lines', 'id')->where('made_to_order', true),
                Rule::unique('items', 'order_form_line_id')->withoutTrashed(),
            ],

            'metal_type_id' => ['required', 'exists:metal_types,id'],
            'purity_id' => ['required', 'exists:purities,id'],
            'making_charge_id' => ['nullable', 'exists:making_charges,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],

            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'huid' => ['nullable', 'string', 'max:20'],

            'gross_weight' => ['required', 'numeric', 'min:0.001', 'max:999999.999'],
            'other_deduction' => ['required', 'numeric', 'min:0', 'max:999999.999'],

            'extra_charge_1' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'extra_charge_1_label' => ['nullable', 'string', 'max:20'],
            'extra_charge_2' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'extra_charge_2_label' => ['nullable', 'string', 'max:20'],

            // The same shape the item form posts, so ItemCalculator handles both.
            'stones' => ['nullable', 'array', 'max:100'],
            'stones.*.stone_master_id' => ['required', 'integer', 'exists:stone_masters,id'],
            'stones.*.pieces' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'stones.*.weight_carat' => ['nullable', 'numeric', 'min:0', 'max:99999.999'],
            'stones.*.weight_grams' => ['nullable', 'numeric', 'min:0', 'max:99999.999'],
            'stones.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'stones.*.deduct_from_gross' => ['boolean'],

            'photo' => ['nullable', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_form_line_id.unique' => 'That line already has a piece made against it.',
            'order_form_line_id.exists' => 'Choose a line that is to be made to order.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $stones = collect($this->input('stones', []))
            ->reject(fn ($row) => blank($row['stone_master_id'] ?? null))
            ->values()
            ->all();

        $this->merge([
            'stones' => $stones,
            'other_deduction' => $this->input('other_deduction') ?: 0,
            // Both columns are NOT NULL with a zero default, so a blank box is zero.
            'extra_charge_1' => $this->input('extra_charge_1') ?: 0,
            'extra_charge_2' => $this->input('extra_charge_2') ?: 0,
        ]);
    }
}
