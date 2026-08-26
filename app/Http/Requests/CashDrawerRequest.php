<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashDrawerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('cash_drawer') ? 'cash_drawer.edit' : 'cash_drawer.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('cash_drawer')?->id;

        return [
            'name' => ['required', 'string', 'max:100',
                Rule::unique('cash_drawers', 'name')->ignore($id)->withoutTrashed()],
            // A till starts at zero or above; a negative opening is a correction,
            // not an opening.
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'opening_balance' => $this->input('opening_balance') ?: 0,
            'sort_order' => $this->input('sort_order') ?: 0,
        ]);
    }
}
