<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RepairFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('repair_form') ? 'repair_form.edit' : 'repair_form.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'form_date' => ['required', 'date'],
            'delivery_date' => ['required', 'date', 'after_or_equal:form_date'],

            'customer_name' => ['required', 'string', 'max:150'],
            'contact_no' => ['required', 'string', 'max:30'],
            'contact_no_alt' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],

            'approx_extra_charge' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'remarks' => ['nullable', 'string', 'max:1000'],

            'sales_person_ids' => ['required', 'array', 'min:1', 'max:10'],
            'sales_person_ids.*' => ['integer', 'exists:sales_persons,id'],

            'lines' => ['required', 'array', 'min:1', 'max:50'],
            // Existing lines post their id so a line whose piece is already back in
            // stock is updated rather than dropped and recreated, which would break
            // the item's link to it.
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.sort_order' => ['required', 'integer', 'min:0'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.net_weight' => ['nullable', 'numeric', 'min:0', 'max:999999.999'],

            'photo' => ['nullable', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:4096'],
            'remove_photo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Add at least one item the customer handed in.',
            'sales_person_ids.required' => 'Choose at least one sales person.',
        ];
    }

    /**
     * Blank rows are dropped before validation, the way the stone, lot and hisab
     * grids already behave — a clerk should not have to tidy rows they never filled.
     */
    protected function prepareForValidation(): void
    {
        $lines = collect($this->input('lines', []))
            ->reject(fn ($row) => blank($row['description'] ?? null) && blank($row['net_weight'] ?? null))
            ->values()
            ->map(fn ($row, $i) => [
                'id' => ($row['id'] ?? null) ?: null,
                'description' => $row['description'] ?? null,
                'net_weight' => ($row['net_weight'] ?? null) === '' ? null : ($row['net_weight'] ?? null),
                'sort_order' => $i,
            ])
            ->all();

        $this->merge([
            'lines' => $lines,
            'approx_extra_charge' => $this->input('approx_extra_charge') === '' ? null : $this->input('approx_extra_charge'),
            'remove_photo' => $this->boolean('remove_photo'),
        ]);
    }
}
