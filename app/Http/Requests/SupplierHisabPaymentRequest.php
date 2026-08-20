<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierHisabPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('supplier_hisab.edit');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rows' => ['present', 'array', 'max:100'],
            'rows.*.item_name' => ['required', 'string', 'max:100'],
            'rows.*.gross_weight' => ['required', 'numeric', 'min:0.001', 'max:999999.999'],
            'rows.*.touch' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * Blank rows are dropped before validation, the way the stone and lot grids
     * already behave — a clerk should not have to tidy up rows they never filled.
     */
    protected function prepareForValidation(): void
    {
        $rows = collect($this->input('rows', []))
            ->reject(fn ($row) => blank($row['item_name'] ?? null) && blank($row['gross_weight'] ?? null))
            ->values()
            ->map(fn ($row, $i) => [
                'item_name' => $row['item_name'] ?? null,
                'gross_weight' => $row['gross_weight'] ?? null,
                'touch' => ($row['touch'] ?? null) === null || $row['touch'] === '' ? 100 : $row['touch'],
                'sort_order' => $i,
            ])
            ->all();

        $this->merge(['rows' => $rows]);
    }
}
