<?php

namespace App\Http\Requests;

use App\Models\OrderFormLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('order_form') ? 'order_form.edit' : 'order_form.create');
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
            'sales_person_id' => ['required', 'exists:sales_persons,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],

            'lines' => ['required', 'array', 'min:1', 'max:50'],
            // Existing lines post their id so one whose piece is already held is
            // updated rather than dropped and recreated, which would break the link.
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.sort_order' => ['required', 'integer', 'min:0'],
            'lines.*.source_item_id' => ['nullable', 'integer', 'exists:items,id'],
            'lines.*.made_to_order' => ['boolean'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.size_pcs' => ['nullable', 'string', 'max:50'],
            'lines.*.metal_type_id' => ['nullable', 'exists:metal_types,id'],
            'lines.*.purity_id' => ['nullable', 'exists:purities,id'],
            'lines.*.net_weight' => ['required', 'numeric', 'min:0', 'max:999999.999'],
            'lines.*.lc_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'lines.*.lc_type' => ['required', Rule::in(array_keys(OrderFormLine::LC_TYPES))],
            'lines.*.oc_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            // Only a stock line can be ticked ready here; a made piece is created on
            // the Order Items screen, which is what sets its link.
            'lines.*.reserve' => ['boolean'],
            // Pinning happens on the form itself: the rate is agreed with the
            // customer as the order is taken, not on a later visit to the edit screen.
            'lines.*.fix_rate' => ['boolean'],

            'lines.*.stones' => ['nullable', 'array', 'max:50'],
            'lines.*.stones.*.stone_master_id' => ['required', 'integer', 'exists:stone_masters,id'],
            'lines.*.stones.*.pieces' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'lines.*.stones.*.weight_carat' => ['nullable', 'numeric', 'min:0', 'max:99999.999'],
            'lines.*.stones.*.weight_grams' => ['nullable', 'numeric', 'min:0', 'max:99999.999'],
            'lines.*.stones.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'lines.*.stones.*.deduct_from_gross' => ['boolean'],

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
            'lines.required' => 'Add at least one piece to the order.',
            'sales_person_id.required' => 'Choose the sales person taking the order.',
        ];
    }

    /**
     * Blank rows are dropped before validation, the way every other grid in this app
     * already behaves — a clerk should not have to tidy rows they never filled.
     */
    protected function prepareForValidation(): void
    {
        $lines = collect($this->input('lines', []))
            ->reject(fn ($row) => blank($row['description'] ?? null) && blank($row['source_item_id'] ?? null))
            ->values()
            ->map(function ($row, $i) {
                $stones = collect($row['stones'] ?? [])
                    ->reject(fn ($stone) => blank($stone['stone_master_id'] ?? null))
                    ->values()
                    ->all();

                return [
                    'id' => ($row['id'] ?? null) ?: null,
                    'sort_order' => $i,
                    'source_item_id' => ($row['source_item_id'] ?? null) ?: null,
                    'made_to_order' => (bool) ($row['made_to_order'] ?? false),
                    'description' => $row['description'] ?? null,
                    'size_pcs' => $row['size_pcs'] ?? null,
                    'metal_type_id' => ($row['metal_type_id'] ?? null) ?: null,
                    'purity_id' => ($row['purity_id'] ?? null) ?: null,
                    'net_weight' => $row['net_weight'] ?: 0,
                    'lc_amount' => $row['lc_amount'] ?: 0,
                    'lc_type' => $row['lc_type'] ?? OrderFormLine::LC_PER_GRAM,
                    'oc_amount' => ($row['oc_amount'] ?? null) ?: 0,
                    'reserve' => (bool) ($row['reserve'] ?? false),
                    'fix_rate' => (bool) ($row['fix_rate'] ?? false),
                    'stones' => $stones,
                ];
            })
            ->all();

        $this->merge(['lines' => $lines, 'remove_photo' => $this->boolean('remove_photo')]);
    }
}
