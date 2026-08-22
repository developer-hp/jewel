<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('order_type') ? 'order_type.edit' : 'order_type.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('order_type')?->id;

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('order_types', 'name')->ignore($id)->withoutTrashed()],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'sort_order' => $this->input('sort_order') ?: 0,
        ]);
    }
}
