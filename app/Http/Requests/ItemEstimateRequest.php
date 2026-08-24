<?php

namespace App\Http\Requests;

use App\Models\ItemEstimateLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('item_estimate') ? 'item_estimate.edit' : 'item_estimate.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estimate_date' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:150'],
            'contact_no' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'sales_person_id' => ['required', 'exists:sales_persons,id'],

            'order_form_id' => ['nullable', 'exists:order_forms,id'],
            // Optional: its document prints as a further page behind this one.
            'og_estimate_id' => ['nullable', 'exists:og_estimates,id'],

            'gst_enabled' => ['boolean'],
            'show_photo' => ['boolean'],

            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.sort_order' => ['required', 'integer', 'min:0'],
            'lines.*.item_id' => ['nullable', 'integer', 'exists:items,id'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.gross_weight' => ['required', 'numeric', 'min:0.001', 'max:999999.999'],
            'lines.*.rate' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'lines.*.labour_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'lines.*.labour_type' => ['required', Rule::in(array_keys(ItemEstimateLine::LABOUR_TYPES))],
            'lines.*.oc_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],

            'lines.*.stones' => ['nullable', 'array', 'max:50'],
            'lines.*.stones.*.stone_master_id' => ['required', 'integer', 'exists:stone_masters,id'],
            'lines.*.stones.*.pieces' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'lines.*.stones.*.weight_grams' => ['nullable', 'numeric', 'min:0', 'max:99999.999'],
            'lines.*.stones.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Add at least one piece to the estimate.',
            'lines.*.gross_weight.min' => 'A line needs a gross weight.',
        ];
    }

    /**
     * Blank rows are dropped, the way every other grid in this app already behaves.
     */
    protected function prepareForValidation(): void
    {
        $lines = collect($this->input('lines', []))
            ->reject(fn ($row) => blank($row['description'] ?? null) && blank($row['gross_weight'] ?? null))
            ->values()
            ->map(function ($row, $i) {
                $stones = collect($row['stones'] ?? [])
                    ->reject(fn ($stone) => blank($stone['stone_master_id'] ?? null))
                    ->values()
                    ->all();

                return [
                    'sort_order' => $i,
                    'item_id' => ($row['item_id'] ?? null) ?: null,
                    'description' => $row['description'] ?? null,
                    'gross_weight' => ($row['gross_weight'] ?? null) ?: 0,
                    'rate' => ($row['rate'] ?? null) ?: 0,
                    'labour_amount' => ($row['labour_amount'] ?? null) ?: 0,
                    'labour_type' => $row['labour_type'] ?? ItemEstimateLine::LABOUR_PER_GRAM,
                    'oc_amount' => ($row['oc_amount'] ?? null) ?: 0,
                    'stones' => $stones,
                ];
            })
            ->all();

        $this->merge([
            'lines' => $lines,
            'gst_enabled' => $this->boolean('gst_enabled'),
            'show_photo' => $this->boolean('show_photo'),
            'order_form_id' => $this->input('order_form_id') ?: null,
            'og_estimate_id' => $this->input('og_estimate_id') ?: null,
        ]);
    }
}
