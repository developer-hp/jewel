<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('stock_group') ? 'stock_group.edit' : 'stock_group.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('stock_group')?->id;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('stock_groups', 'name')->ignore($id)->withoutTrashed()],
            'code' => ['required', 'string', 'alpha_num', 'max:20', Rule::unique('stock_groups', 'code')->ignore($id)->withoutTrashed()],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper((string) $this->input('code')),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => $this->input('sort_order') ?: 0,
        ]);
    }
}
