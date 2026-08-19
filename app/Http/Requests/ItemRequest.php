<?php

namespace App\Http\Requests;

use App\Models\Purity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('item') ? 'item.edit' : 'item.create');
    }

    /**
     * Note what is absent: net_weight and the stone/diamond weight columns. Those
     * are derived by App\Services\ItemCalculator and never accepted from input.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'item_group_id' => ['required', 'exists:item_groups,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'metal_type_id' => ['required', 'exists:metal_types,id'],
            'purity_id' => ['required', 'exists:purities,id'],
            'making_charge_id' => ['nullable', 'exists:making_charges,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'gross_weight' => ['required', 'numeric', 'gt:0', 'max:99999.999'],
            'other_deduction' => ['nullable', 'numeric', 'min:0', 'max:99999.999'],
            'is_active' => ['boolean'],

            'extra_charge_1' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'extra_charge_1_label' => ['nullable', 'string', 'max:20'],
            'extra_charge_2' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'extra_charge_2_label' => ['nullable', 'string', 'max:20'],

            'stones' => ['array'],
            'stones.*.stone_master_id' => ['required', 'exists:stone_masters,id'],
            'stones.*.pieces' => ['nullable', 'integer', 'min:0', 'max:100000'],
            // Either unit may be filled; the calculator converts grams back to carat.
            'stones.*.weight_carat' => ['nullable', 'numeric', 'min:0', 'max:99999.999'],
            'stones.*.weight_grams' => ['nullable', 'numeric', 'min:0', 'max:99999.999'],
            'stones.*.rate' => ['nullable', 'numeric', 'min:0'],
            'stones.*.deduct_from_gross' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $purityId = $this->input('purity_id');
            $metalTypeId = $this->input('metal_type_id');

            if (! $purityId || ! $metalTypeId) {
                return;
            }

            // The purity dropdown is filtered client-side; re-check it server-side so a
            // tampered or stale form cannot pair 22K gold with the Silver metal type.
            $belongs = Purity::whereKey($purityId)
                ->where('metal_type_id', $metalTypeId)
                ->exists();

            if (! $belongs) {
                $validator->errors()->add('purity_id', 'The selected purity does not belong to the selected metal type.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'other_deduction' => $this->input('other_deduction') ?: 0,
            'extra_charge_1' => $this->input('extra_charge_1') ?: 0,
            'extra_charge_2' => $this->input('extra_charge_2') ?: 0,
            'stones' => $this->normalisedStoneRows(),
        ]);
    }

    /**
     * Drop blank repeater rows the user added but never filled in.
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalisedStoneRows(): array
    {
        return collect($this->input('stones', []))
            ->filter(fn ($row) => is_array($row) && ! blank($row['stone_master_id'] ?? null))
            ->map(fn (array $row) => [
                'stone_master_id' => $row['stone_master_id'],
                'pieces' => $row['pieces'] ?? 0,
                'weight_carat' => $row['weight_carat'] ?? 0,
                'weight_grams' => $row['weight_grams'] ?? 0,
                'rate' => $row['rate'] ?? null,
                'deduct_from_gross' => (bool) ($row['deduct_from_gross'] ?? false),
            ])
            ->values()
            ->all();
    }
}
