<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierHisabRequest extends FormRequest
{
    /**
     * The add/edit form is a modal on the listing, so its errors are kept in their
     * own bag — the listing's other forms must not light up when it fails.
     */
    protected $errorBag = 'hisab';

    public function authorize(): bool
    {
        return $this->user()->can($this->route('hisab') ? 'supplier_hisab.edit' : 'supplier_hisab.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hisab_date' => ['required', 'date'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'fine_baki' => ['required', 'numeric', 'min:0', 'max:999999.999'],
            'cash_baki' => ['required', 'numeric', 'min:0', 'max:99999999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'supplier_id' => 'supplier',
            'fine_baki' => 'gold weight',
            'cash_baki' => 'amount',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Both boxes are optional at the counter; blank means nothing owed.
        $this->merge([
            'fine_baki' => $this->input('fine_baki') ?: 0,
            'cash_baki' => $this->input('cash_baki') ?: 0,
        ]);
    }
}
