<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InternalStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('internal_stock') ? 'internal_stock.edit' : 'internal_stock.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('internal_stock')?->id;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('internal_stocks', 'name')->ignore($id)->withoutTrashed()],
            'reset_on_opening' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reset_on_opening' => $this->boolean('reset_on_opening'),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => $this->input('sort_order') ?: 0,
        ]);
    }
}
